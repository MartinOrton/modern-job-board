<?php
/**
 * Admin Jobs tab: saved views, search, filters, row and bulk actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Jobs
{
    const PER_PAGE_DEFAULT = 20;
    const USER_NOTICE_META = 'mjb_jobs_dismiss_apps_notice';
    const USER_PER_PAGE_META = 'mjb_jobs_per_page';

    /** @var array<int, array<string, mixed>>|null */
    private static $catalog = null;

    /**
     * Hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_ajax_mjb_admin_job_action', array(__CLASS__, 'ajax_action'));
        add_action('wp_ajax_mjb_admin_jobs_dismiss_notice', array(__CLASS__, 'ajax_dismiss_notice'));
        add_action('wp_ajax_mjb_admin_jobs_suggest', array(__CLASS__, 'ajax_suggest'));
        add_action('admin_post_mjb_export_jobs', array(__CLASS__, 'handle_export'));
    }

    /**
     * Allowed per-page sizes.
     *
     * @return int[]
     */
    public static function per_page_options()
    {
        return array(5, 10, 20, 50, 100);
    }

    /**
     * Saved-view ids. Includes dashboard list filters so those links land here.
     *
     * @return string[]
     */
    public static function view_ids()
    {
        return array(
            'all',
            'attention',
            'expiring',
            'zero_views',
            'no_apps',
            'published',
            'pending',
            'draft',
            'expired',
            'filled',
        );
    }

    /**
     * Sanitize a view id. Dashboard `mjb_list` values stay valid.
     *
     * @param string $view
     * @return string
     */
    public static function sanitize_view($view)
    {
        $view = sanitize_key((string) $view);
        if ($view === '' || $view === 'noviews') {
            $view = 'zero_views';
        }

        return in_array($view, self::view_ids(), true) ? $view : 'all';
    }

    /**
     * @param mixed $per
     * @return int
     */
    public static function sanitize_per_page($per)
    {
        $per = (int) $per;

        return in_array($per, self::per_page_options(), true) ? $per : self::PER_PAGE_DEFAULT;
    }

    /**
     * @param mixed $orderby
     * @return string
     */
    public static function sanitize_orderby($orderby)
    {
        $orderby = sanitize_key((string) $orderby);
        $allowed = array('title', 'status', 'views', 'apps', 'posted');

        return in_array($orderby, $allowed, true) ? $orderby : 'posted';
    }

    /**
     * @param mixed $order
     * @return string
     */
    public static function sanitize_order($order)
    {
        $order = strtolower((string) $order);

        return $order === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Read list state from the current request.
     *
     * @param int $page
     * @return array<string, mixed>
     */
    public static function get_state($page = 1)
    {
        $view = 'all';
        if (isset($_REQUEST['mjb_list'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
            $view = self::sanitize_view(wp_unslash($_REQUEST['mjb_list']));
        }

        $q = '';
        if (isset($_REQUEST['mjb_q'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
            $q = sanitize_text_field(wp_unslash($_REQUEST['mjb_q']));
        }

        $company = 0;
        if (isset($_REQUEST['mjb_company'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $company = absint($_REQUEST['mjb_company']);
        }

        $category = '';
        if (isset($_REQUEST['mjb_cat'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $category = sanitize_title(wp_unslash($_REQUEST['mjb_cat']));
        }

        $type = '';
        if (isset($_REQUEST['mjb_type'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $type = sanitize_title(wp_unslash($_REQUEST['mjb_type']));
        }

        $orderby = 'posted';
        if (isset($_REQUEST['mjb_orderby'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort.
            $orderby = self::sanitize_orderby(wp_unslash($_REQUEST['mjb_orderby']));
        }

        $order = 'desc';
        if (isset($_REQUEST['mjb_order'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort.
            $order = self::sanitize_order(wp_unslash($_REQUEST['mjb_order']));
        }

        $per = self::PER_PAGE_DEFAULT;
        if (isset($_REQUEST['mjb_per'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page size.
            $per = self::sanitize_per_page(wp_unslash($_REQUEST['mjb_per']));
            if (function_exists('update_user_meta') && get_current_user_id()) {
                update_user_meta(get_current_user_id(), self::USER_PER_PAGE_META, $per);
            }
        } elseif (function_exists('get_user_meta') && get_current_user_id()) {
            $saved = (int) get_user_meta(get_current_user_id(), self::USER_PER_PAGE_META, true);
            if (in_array($saved, self::per_page_options(), true)) {
                $per = $saved;
            }
        }

        return array(
            'view' => $view,
            'q' => $q,
            'company' => $company,
            'category' => $category,
            'type' => $type,
            'orderby' => $orderby,
            'order' => $order,
            'per' => $per,
            'page' => max(1, (int) $page),
        );
    }

    /**
     * Query jobs for the current state.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public static function query($state)
    {
        $catalog = self::catalog();
        $matched = array();

        foreach ($catalog as $row) {
            if (!self::row_matches_state($row, $state)) {
                continue;
            }
            $matched[] = $row;
        }

        $matched = self::sort_rows($matched, $state['orderby'], $state['order']);

        $total = count($matched);
        $pages = max(1, (int) ceil($total / max(1, (int) $state['per'])));
        $page = min((int) $state['page'], $pages);
        $offset = ($page - 1) * (int) $state['per'];
        $rows = array_slice($matched, $offset, (int) $state['per']);

        return array(
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => self::view_counts($catalog),
            'filters' => self::filter_options($catalog),
            'notice' => self::apps_notice($catalog),
            'expiring' => (int) self::view_counts($catalog)['expiring'],
        );
    }

    /**
     * Whether a catalog row belongs in the current view/search/filters.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $state
     * @return bool
     */
    public static function row_matches_state($row, $state)
    {
        if (!self::row_matches_view($row, $state['view'])) {
            return false;
        }

        if ((int) $state['company'] > 0 && (int) $row['company_id'] !== (int) $state['company']) {
            return false;
        }

        if ($state['category'] !== '' && $row['category_slug'] !== $state['category']) {
            return false;
        }

        if ($state['type'] !== '' && $row['type_slug'] !== $state['type']) {
            return false;
        }

        $q = strtolower(trim((string) $state['q']));
        if ($q === '') {
            return true;
        }

        $hay = strtolower(
            $row['title'] . ' ' . $row['company'] . ' ' . $row['location'] . ' ' . $row['type'] . ' ' . $row['category']
        );

        return strpos($hay, $q) !== false;
    }

    /**
     * Predictive suggestions for the Jobs search field (title, company, location).
     *
     * Empty query returns the most recently posted jobs, matching Filter Jobs keywords.
     * Uses a light title/company/location scan instead of the full Jobs catalog.
     *
     * @param string $q
     * @param int    $limit
     * @return array<int, array{value:string,label:string,kind:string,hint:string}>
     */
    public static function suggest($q, $limit = 12)
    {
        $q = strtolower(trim((string) $q));
        $limit = max(1, min(25, (int) $limit));
        $scan = $q === '' ? max($limit * 4, 24) : 120;

        $ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'private'),
            'posts_per_page' => $scan,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ));

        $jobs = array();
        $companies = array();
        $locations = array();
        $seen_titles = array();
        $seen_companies = array();
        $seen_locations = array();

        foreach ((array) $ids as $job_id) {
            $job_id = (int) $job_id;
            $title = (string) get_the_title($job_id);
            $company_id = (int) get_post_meta($job_id, '_company_id', true);
            $company = $company_id ? (string) get_the_title($company_id) : (string) get_post_meta($job_id, '_company_name', true);
            $location = class_exists('MJB_Location') ? (string) MJB_Location::format_job_location($job_id) : '';
            $posted_raw = get_the_date('U', $job_id);
            $posted = is_numeric($posted_raw) ? (int) $posted_raw : (int) strtotime((string) $posted_raw);

            $title_rank = self::match_rank($title, $q);
            $company_rank = self::match_rank($company, $q);
            $location_rank = self::match_rank($location, $q);
            $job_rank = $q === '' ? $title_rank : max($title_rank, $company_rank, $location_rank);

            if ($job_rank > 0 && $title !== '') {
                $title_key = strtolower($title);
                if (!isset($seen_titles[$title_key])) {
                    $seen_titles[$title_key] = true;
                    $hint_parts = array();
                    if ($company !== '') {
                        $hint_parts[] = $company;
                    }
                    if ($location !== '') {
                        $hint_parts[] = $location;
                    }
                    $jobs[] = array(
                        'value' => $title,
                        'label' => $title,
                        'kind' => 'job',
                        'hint' => implode(' · ', $hint_parts),
                        'rank' => $job_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q !== '' && $company_rank > 0) {
                $company_key = strtolower($company);
                if (!isset($seen_companies[$company_key])) {
                    $seen_companies[$company_key] = true;
                    $companies[] = array(
                        'value' => $company,
                        'label' => $company,
                        'kind' => 'company',
                        'hint' => __('Company', 'modern-job-board'),
                        'rank' => $company_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q !== '' && $location_rank > 0) {
                $location_key = strtolower($location);
                if (!isset($seen_locations[$location_key])) {
                    $seen_locations[$location_key] = true;
                    $locations[] = array(
                        'value' => $location,
                        'label' => $location,
                        'kind' => 'location',
                        'hint' => __('Location', 'modern-job-board'),
                        'rank' => $location_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q === '' && count($jobs) >= $limit) {
                break;
            }
        }

        $groups = $q === '' ? array($jobs) : array($jobs, $companies, $locations);
        $items = array();
        foreach ($groups as $group) {
            usort($group, static function ($a, $b) use ($q) {
                if ($q === '') {
                    return ((int) $b['posted_ts']) <=> ((int) $a['posted_ts']);
                }
                $rank = ((int) $b['rank']) <=> ((int) $a['rank']);
                if ($rank !== 0) {
                    return $rank;
                }

                return ((int) $b['posted_ts']) <=> ((int) $a['posted_ts']);
            });
            foreach ($group as $item) {
                $items[] = array(
                    'value' => (string) $item['value'],
                    'label' => (string) $item['label'],
                    'kind' => (string) $item['kind'],
                    'hint' => (string) $item['hint'],
                );
                if (count($items) >= $limit) {
                    return $items;
                }
            }
        }

        return $items;
    }

    /**
     * @param string $haystack
     * @param string $q        Already lowercased.
     * @return int 0 = no match, 1 = contains, 2 = starts with, 3 = exact.
     */
    public static function match_rank($haystack, $q)
    {
        $hay = strtolower(trim((string) $haystack));
        if ($hay === '') {
            return 0;
        }
        if ($q === '') {
            return 1;
        }
        if ($hay === $q) {
            return 3;
        }
        if (strpos($hay, $q) === 0) {
            return 2;
        }
        if (strpos($hay, $q) !== false) {
            return 1;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $view
     * @return bool
     */
    public static function row_matches_view($row, $view)
    {
        $view = self::sanitize_view($view);
        switch ($view) {
            case 'attention':
                return !empty($row['needs_attention']);
            case 'expiring':
                return !empty($row['expiring']);
            case 'zero_views':
                return (int) $row['views'] === 0 && in_array($row['status'], array('publish', 'expired'), true);
            case 'no_apps':
                return (int) $row['applications'] === 0 && $row['status'] === 'publish' && empty($row['external']);
            case 'published':
                return $row['status'] === 'publish' && empty($row['filled']);
            case 'pending':
                return $row['status'] === 'pending';
            case 'draft':
                return $row['status'] === 'draft';
            case 'expired':
                return $row['status'] === 'expired';
            case 'filled':
                return !empty($row['filled']);
            case 'all':
            default:
                return true;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param string                           $orderby
     * @param string                           $order
     * @return array<int, array<string, mixed>>
     */
    public static function sort_rows($rows, $orderby, $order)
    {
        $orderby = self::sanitize_orderby($orderby);
        $dir = self::sanitize_order($order) === 'asc' ? 1 : -1;
        $status_rank = array(
            'draft' => 0,
            'pending' => 1,
            'publish' => 2,
            'filled' => 3,
            'expired' => 4,
            'private' => 5,
        );

        usort($rows, static function ($a, $b) use ($orderby, $dir, $status_rank) {
            if ($orderby === 'title') {
                return strcasecmp((string) $a['title'], (string) $b['title']) * $dir;
            }
            if ($orderby === 'status') {
                $as = isset($status_rank[$a['list_status']]) ? $status_rank[$a['list_status']] : 9;
                $bs = isset($status_rank[$b['list_status']]) ? $status_rank[$b['list_status']] : 9;
                return ($as <=> $bs) * $dir;
            }
            if ($orderby === 'views') {
                return (((int) $a['views']) <=> ((int) $b['views'])) * $dir;
            }
            if ($orderby === 'apps') {
                return (((int) $a['applications']) <=> ((int) $b['applications'])) * $dir;
            }

            return (((int) $a['posted_ts']) <=> ((int) $b['posted_ts'])) * $dir;
        });

        return $rows;
    }

    /**
     * Default listing duration used for extend / republish.
     *
     * @return int
     */
    public static function listing_duration_days()
    {
        $days = (int) get_option('mjb_listing_duration', 30);

        return $days > 0 ? $days : 30;
    }

    /**
     * Extend _job_expires by the board listing duration.
     *
     * @param int $job_id
     * @return string New expiry Y-m-d, or empty on failure.
     */
    public static function extend_expiry($job_id)
    {
        $job_id = (int) $job_id;
        if ($job_id < 1) {
            return '';
        }

        $days = self::listing_duration_days();
        $now = (int) current_time('timestamp');
        $current = (string) get_post_meta($job_id, '_job_expires', true);
        $base = $current !== '' ? strtotime($current) : 0;
        if (!$base || $base < $now) {
            $base = $now;
        }
        $next = $base + ($days * DAY_IN_SECONDS);
        $value = gmdate('Y-m-d', $next);
        update_post_meta($job_id, '_job_expires', $value);

        return $value;
    }

    /**
     * Publish a pending/draft/expired job, respecting the Free cap.
     *
     * @param int $job_id
     * @return true|WP_Error
     */
    public static function publish_job($job_id)
    {
        $job_id = (int) $job_id;
        $post = get_post($job_id);
        if (!$post || $post->post_type !== 'job_listing') {
            return new WP_Error('invalid_job', __('Job not found.', 'modern-job-board'));
        }

        if (!MJB_License::can_publish_job($job_id)) {
            return new WP_Error(
                'plan_limit',
                __('Free plan limit reached. Upgrade to publish more jobs.', 'modern-job-board')
            );
        }

        $updated = wp_update_post(array(
            'ID' => $job_id,
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return true;
    }

    /**
     * Republish an expired (or filled) listing for another duration window.
     *
     * @param int $job_id
     * @return true|WP_Error
     */
    public static function republish_job($job_id)
    {
        $result = self::publish_job($job_id);
        if (is_wp_error($result)) {
            return $result;
        }

        if (class_exists('MJB_Job_Ops')) {
            MJB_Job_Ops::set_filled($job_id, false);
        }
        self::extend_expiry($job_id);

        return true;
    }

    /**
     * Duplicate a listing as a draft.
     *
     * @param int $job_id
     * @return int|WP_Error New post ID.
     */
    public static function duplicate_job($job_id)
    {
        $job_id = (int) $job_id;
        $post = get_post($job_id);
        if (!$post || $post->post_type !== 'job_listing') {
            return new WP_Error('invalid_job', __('Job not found.', 'modern-job-board'));
        }

        $title = (string) $post->post_title;
        $copy_title = sprintf(
            /* translators: %s: original job title */
            __('%s (Copy)', 'modern-job-board'),
            $title
        );

        $new_id = wp_insert_post(array(
            'post_type' => 'job_listing',
            'post_status' => 'draft',
            'post_title' => $copy_title,
            'post_content' => isset($post->post_content) ? $post->post_content : '',
            'post_excerpt' => isset($post->post_excerpt) ? $post->post_excerpt : '',
            'post_author' => isset($post->post_author) ? (int) $post->post_author : get_current_user_id(),
        ), true);

        if (is_wp_error($new_id) || !(int) $new_id) {
            return is_wp_error($new_id)
                ? $new_id
                : new WP_Error('duplicate_failed', __('Could not duplicate the job.', 'modern-job-board'));
        }

        $new_id = (int) $new_id;
        $skip = array('_mjb_view_count', '_job_views', '_mjb_views', '_job_filled', '_featured', '_job_expires', '_job_publish_at');
        $meta = get_post_meta($job_id);
        if (is_array($meta)) {
            foreach ($meta as $key => $values) {
                if (!is_string($key) || $key === '' || in_array($key, $skip, true)) {
                    continue;
                }
                $list = is_array($values) ? $values : array($values);
                foreach ($list as $value) {
                    if (is_string($value) && function_exists('is_serialized') && is_serialized($value) && function_exists('maybe_unserialize')) {
                        $value = maybe_unserialize($value);
                    }
                    update_post_meta($new_id, $key, $value);
                }
            }
        }

        $taxes = array('job_category', 'job_type', 'job_location');
        if (function_exists('get_object_taxonomies')) {
            $registered = get_object_taxonomies('job_listing');
            if (is_array($registered) && $registered) {
                $taxes = $registered;
            }
        }
        foreach ($taxes as $taxonomy) {
            $terms = wp_get_post_terms($job_id, $taxonomy, array('fields' => 'names'));
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            wp_set_object_terms($new_id, $terms, $taxonomy);
        }

        return $new_id;
    }

    /**
     * AJAX row/bulk action.
     *
     * @return void
     */
    public static function ajax_action()
    {
        check_ajax_referer('mjb_admin_tabs', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }

        $action = isset($_POST['job_action']) ? sanitize_key(wp_unslash($_POST['job_action'])) : '';
        $ids = array();
        if (isset($_POST['job_ids']) && is_array($_POST['job_ids'])) {
            $ids = array_values(array_filter(array_map('absint', wp_unslash($_POST['job_ids']))));
        } elseif (isset($_POST['job_id'])) {
            $ids = array(absint($_POST['job_id']));
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids) || $action === '') {
            wp_send_json_error(array('message' => __('Nothing to do.', 'modern-job-board')), 400);
        }

        $ok = 0;
        $errors = array();
        $extra = array();

        foreach ($ids as $job_id) {
            $post = get_post($job_id);
            if (!$post || $post->post_type !== 'job_listing') {
                $errors[] = __('Job not found.', 'modern-job-board');
                continue;
            }

            $result = self::run_action($action, $job_id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }
            $ok++;
            if ($action === 'duplicate' && is_int($result)) {
                $extra['duplicate_id'] = $result;
                $extra['edit_url'] = get_edit_post_link($result, 'raw');
            }
        }

        if ($ok === 0) {
            wp_send_json_error(array(
                'message' => $errors ? $errors[0] : __('Could not update jobs.', 'modern-job-board'),
            ), 400);
        }

        wp_send_json_success(array(
            'updated' => $ok,
            'errors' => $errors,
            'message' => self::action_message($action, $ok),
            'extra' => $extra,
        ));
    }

    /**
     * @param string $action
     * @param int    $job_id
     * @return true|int|WP_Error
     */
    public static function run_action($action, $job_id)
    {
        $job_id = (int) $job_id;
        switch ($action) {
            case 'extend':
                self::extend_expiry($job_id);
                return true;
            case 'feature':
                update_post_meta($job_id, '_featured', 1);
                return true;
            case 'unfeature':
                update_post_meta($job_id, '_featured', 0);
                return true;
            case 'fill':
                if (class_exists('MJB_Job_Ops')) {
                    MJB_Job_Ops::set_filled($job_id, true);
                } else {
                    update_post_meta($job_id, '_job_filled', '1');
                }
                return true;
            case 'unfill':
                if (class_exists('MJB_Job_Ops')) {
                    MJB_Job_Ops::set_filled($job_id, false);
                } else {
                    update_post_meta($job_id, '_job_filled', '0');
                }
                return true;
            case 'publish':
            case 'approve':
                return self::publish_job($job_id);
            case 'republish':
                return self::republish_job($job_id);
            case 'duplicate':
                return self::duplicate_job($job_id);
            case 'delete':
                if (!current_user_can('delete_post', $job_id)) {
                    return new WP_Error('forbidden', __('You cannot delete this job.', 'modern-job-board'));
                }
                if (function_exists('wp_trash_post')) {
                    $trashed = wp_trash_post($job_id);
                    if ($trashed) {
                        return true;
                    }
                }
                return wp_delete_post($job_id, true) ? true : new WP_Error('delete_failed', __('Could not delete the job.', 'modern-job-board'));
            default:
                return new WP_Error('unknown_action', __('Unknown action.', 'modern-job-board'));
        }
    }

    /**
     * Dismiss the zero-applications notice for this user.
     *
     * @return void
     */
    public static function ajax_dismiss_notice()
    {
        check_ajax_referer('mjb_admin_tabs', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }
        $user_id = get_current_user_id();
        if ($user_id) {
            update_user_meta($user_id, self::USER_NOTICE_META, '1');
        }
        wp_send_json_success();
    }

    /**
     * AJAX suggestions for the Jobs search field.
     *
     * @return void
     */
    public static function ajax_suggest()
    {
        check_ajax_referer('mjb_admin_tabs', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }

        $q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
        $limit = isset($_REQUEST['limit']) ? max(1, min(25, (int) $_REQUEST['limit'])) : 12;

        wp_send_json_success(array(
            'items' => self::suggest($q, $limit),
        ));
    }

    /**
     * CSV export of the current view or selected IDs.
     *
     * @return void
     */
    public static function handle_export()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }
        check_admin_referer('mjb_export_jobs');

        $ids = array();
        if (isset($_REQUEST['job_ids'])) {
            $raw = wp_unslash($_REQUEST['job_ids']);
            if (is_array($raw)) {
                $ids = array_values(array_filter(array_map('absint', $raw)));
            } else {
                $ids = array_values(array_filter(array_map('absint', explode(',', (string) $raw))));
            }
        }

        if (empty($ids)) {
            $state = self::get_state(1);
            $state['per'] = 100000;
            $state['page'] = 1;
            $query = self::query($state);
            foreach ($query['rows'] as $row) {
                $ids[] = (int) $row['id'];
            }
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=mjb-jobs-' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('job_id', 'title', 'status', 'company', 'location', 'type', 'applications', 'views', 'expires', 'created'));

        $catalog = self::catalog();
        $by_id = array();
        foreach ($catalog as $row) {
            $by_id[(int) $row['id']] = $row;
        }

        foreach ($ids as $id) {
            if (!isset($by_id[$id])) {
                continue;
            }
            $row = $by_id[$id];
            fputcsv($out, array(
                $row['id'],
                $row['title'],
                $row['list_status'],
                $row['company'],
                $row['location'],
                $row['type'],
                $row['applications'],
                $row['views'],
                $row['expires'],
                $row['posted_ymd'],
            ));
        }
        fclose($out);
        exit;
    }

    /**
     * Render the Jobs tab.
     *
     * @param int $page
     * @return void
     */
    public static function render($page = 1)
    {
        $state = self::get_state($page);
        $data = self::query($state);
        $state['page'] = $data['page'];
        $counts = $data['counts'];
        $rows = $data['rows'];

        $sort_for = static function ($key) use ($state) {
            if ($state['orderby'] !== $key) {
                return 'none';
            }

            return $state['order'] === 'asc' ? 'ascending' : 'descending';
        };

        $from = $data['total'] === 0 ? 0 : ((($data['page'] - 1) * $state['per']) + 1);
        $to = min($data['total'], $from + count($rows) - 1);
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--jobs mjb-jobs"
             data-tab-panel="jobs"
             data-view="<?php echo esc_attr($state['view']); ?>"
             data-q="<?php echo esc_attr($state['q']); ?>"
             data-company="<?php echo (int) $state['company'] > 0 ? esc_attr((string) $state['company']) : ''; ?>"
             data-cat="<?php echo esc_attr($state['category']); ?>"
             data-type="<?php echo esc_attr($state['type']); ?>"
             data-orderby="<?php echo esc_attr($state['orderby']); ?>"
             data-order="<?php echo esc_attr($state['order']); ?>"
             data-per="<?php echo esc_attr((string) $state['per']); ?>"
             data-page="<?php echo esc_attr((string) $state['page']); ?>"
             data-total="<?php echo esc_attr((string) $data['total']); ?>">

            <div class="mjb-sec mjb-jobs__sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('briefcase', 22);
                    esc_html_e('Jobs', 'modern-job-board');
                ?></h2>
                <?php if ((int) $data['expiring'] > 0) : ?>
                <span class="mjb-hint mjb-hint--warn"><?php echo esc_html(sprintf(
                    _n('%d expiring within 7 days', '%d expiring within 7 days', (int) $data['expiring'], 'modern-job-board'),
                    (int) $data['expiring']
                )); ?></span>
                <?php endif; ?>
                <span class="mjb-topbar__spacer"></span>
                <div class="mjb-range" role="group" aria-label="<?php esc_attr_e('Row density', 'modern-job-board'); ?>" id="mjb-jobs-density">
                    <span class="mjb-range__thumb" aria-hidden="true"></span>
                    <button type="button" aria-pressed="true" data-density="comfortable"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('menu', 15);
                        esc_html_e('Comfortable', 'modern-job-board');
                    ?></button>
                    <button type="button" aria-pressed="false" data-density="compact"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('list', 15);
                        esc_html_e('Compact', 'modern-job-board');
                    ?></button>
                </div>
            </div>

            <?php if ($data['notice']) : ?>
            <div class="mjb-jobs-notice" id="mjb-jobs-apps-notice" role="status">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                echo MJB_Icons::render('triangle-alert', 20);
                ?>
                <div class="mjb-jobs-notice__txt">
                    <b><?php echo esc_html($data['notice']['title']); ?></b>
                    <span><?php echo esc_html($data['notice']['body']); ?></span>
                </div>
                <a class="mjb-btn mjb-btn-outline mjb-js-tab" href="<?php echo esc_url($data['notice']['url']); ?>" data-tab="<?php echo esc_attr($data['notice']['tab']); ?>"<?php
                    echo !empty($data['notice']['tab_args']['settings_tab'])
                        ? ' data-settings-tab="' . esc_attr($data['notice']['tab_args']['settings_tab']) . '"'
                        : '';
                ?>><?php echo esc_html($data['notice']['action']); ?></a>
                <button class="mjb-jobs-notice__close" type="button" aria-label="<?php esc_attr_e('Dismiss this message', 'modern-job-board'); ?>">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('x', 16);
                    ?>
                </button>
            </div>
            <?php endif; ?>

            <div class="mjb-jobs-views" role="tablist" aria-label="<?php esc_attr_e('Saved views', 'modern-job-board'); ?>">
                <?php
                self::render_view_pill('all', __('All jobs', 'modern-job-board'), $counts['all'], $state['view'], false);
                self::render_view_pill('attention', __('Needs attention', 'modern-job-board'), $counts['attention'], $state['view'], $counts['attention'] > 0);
                self::render_view_pill('expiring', __('Expiring soon', 'modern-job-board'), $counts['expiring'], $state['view'], $counts['expiring'] > 0);
                self::render_view_pill('zero_views', __('No views', 'modern-job-board'), $counts['zero_views'], $state['view'], false);
                self::render_view_pill('no_apps', __('No applications', 'modern-job-board'), $counts['no_apps'], $state['view'], false);
                $status_views = array('published', 'pending', 'draft', 'expired', 'filled');
                $show_status = false;
                foreach ($status_views as $status_view) {
                    if (self::should_show_view_pill($status_view, $counts[$status_view], $state['view'])) {
                        $show_status = true;
                        break;
                    }
                }
                if ($show_status) {
                    echo '<span class="mjb-jobs-views__rule" aria-hidden="true"></span>';
                    self::render_view_pill('published', __('Published', 'modern-job-board'), $counts['published'], $state['view'], false);
                    self::render_view_pill('pending', __('Pending review', 'modern-job-board'), $counts['pending'], $state['view'], false);
                    self::render_view_pill('draft', __('Drafts', 'modern-job-board'), $counts['draft'], $state['view'], false);
                    self::render_view_pill('expired', __('Expired', 'modern-job-board'), $counts['expired'], $state['view'], false);
                    self::render_view_pill('filled', __('Filled', 'modern-job-board'), $counts['filled'], $state['view'], false);
                }
                ?>
            </div>

            <div class="mjb-jobs-toolbar">
                <div class="mjb-jobs-search mjb-ac<?php echo $state['q'] !== '' ? ' is-filled' : ''; ?>" id="mjb-jobs-search" data-mjb-ac="admin_jobs" data-free-text="1">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('search', 16);
                    ?>
                    <input type="text" class="mjb-ac__input" id="mjb-jobs-q" value="<?php echo esc_attr($state['q']); ?>" placeholder="<?php esc_attr_e('Search title, company or location', 'modern-job-board'); ?>" aria-label="<?php esc_attr_e('Search jobs', 'modern-job-board'); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mjb-jobs-ac-list" aria-haspopup="listbox">
                    <button class="mjb-jobs-search__clear" type="button" aria-label="<?php esc_attr_e('Clear search', 'modern-job-board'); ?>">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('x', 14);
                        ?>
                    </button>
                    <div id="mjb-jobs-ac-list" class="mjb-ac__menu mjb-jobs-ac" data-mjb-ac-menu hidden role="listbox"></div>
                </div>
                <?php self::render_filter_menu('company', __('Company', 'modern-job-board'), $data['filters']['companies'], (string) $state['company']); ?>
                <?php self::render_filter_menu('cat', __('Category', 'modern-job-board'), $data['filters']['categories'], $state['category']); ?>
                <?php self::render_filter_menu('type', __('Type', 'modern-job-board'), $data['filters']['types'], $state['type']); ?>
                <span class="mjb-topbar__spacer"></span>
                <span class="mjb-jobs-toolbar__meta" id="mjb-jobs-result-meta"><?php echo esc_html(self::result_meta_text($state, $data['total'])); ?></span>
            </div>

            <div class="mjb-panel mjb-jobs-panel" id="mjb-jobs-panel" data-density="comfortable">
                <?php if (empty($rows)) : ?>
                <div class="mjb-empty mjb-jobs-empty">
                    <div class="mjb-empty__icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('search', 20);
                    ?></div>
                    <b><?php esc_html_e('Nothing matches this view', 'modern-job-board'); ?></b>
                    <p><?php echo esc_html($state['q'] !== ''
                        ? sprintf(
                            /* translators: %s: search query */
                            __('No job matches “%s”.', 'modern-job-board'),
                            $state['q']
                        )
                        : __('Try a different view, or widen the filters.', 'modern-job-board')
                    ); ?></p>
                    <button class="mjb-btn mjb-btn-outline" type="button" id="mjb-jobs-reset"><?php esc_html_e('Clear search and filters', 'modern-job-board'); ?></button>
                </div>
                <?php else : ?>
                <div class="mjb-jobs-table-wrap">
                    <table class="mjb-jobs-table" id="mjb-jobs-table">
                        <caption class="mjb-sr-only"><?php echo esc_html(sprintf(
                            /* translators: 1: shown count, 2: total */
                            __('Job listings, %1$d of %2$d', 'modern-job-board'),
                            count($rows),
                            $data['total']
                        )); ?></caption>
                        <thead>
                            <tr>
                                <th class="mjb-jobs-col-check" scope="col">
                                    <input type="checkbox" id="mjb-jobs-sel-all" aria-label="<?php esc_attr_e('Select all jobs on this page', 'modern-job-board'); ?>">
                                </th>
                                <?php self::render_sort_th('title', 'text', 'mjb-jobs-col-job', __('Job', 'modern-job-board'), $sort_for('title')); ?>
                                <?php self::render_sort_th('status', 'text', 'mjb-jobs-col-status', __('Status', 'modern-job-board'), $sort_for('status')); ?>
                                <?php self::render_sort_th('apps', 'num', 'mjb-jobs-col-num', __('Applications', 'modern-job-board'), $sort_for('apps')); ?>
                                <?php self::render_sort_th('views', 'num', 'mjb-jobs-col-num', __('Views', 'modern-job-board'), $sort_for('views')); ?>
                                <?php self::render_sort_th('posted', 'num', 'mjb-jobs-col-date', __('Posted', 'modern-job-board'), $sort_for('posted')); ?>
                                <th class="mjb-jobs-col-act" scope="col"><?php esc_html_e('Actions', 'modern-job-board'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row) : ?>
                                <?php self::render_row($row); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mjb-jobs-foot" data-per="<?php echo esc_attr((string) $state['per']); ?>">
                    <span class="mjb-jobs-foot__meta"><?php echo wp_kses(
                        sprintf(
                            /* translators: 1: first index, 2: last index, 3: total */
                            __('Showing <b>%1$s–%2$s</b> of <b>%3$s</b> jobs', 'modern-job-board'),
                            number_format_i18n($from),
                            number_format_i18n($to),
                            number_format_i18n($data['total'])
                        ),
                        array('b' => array())
                    ); ?></span>
                    <span class="mjb-topbar__spacer"></span>
                    <div class="mjb-jobs-perpage">
                        <span id="mjb-jobs-per-label"><?php esc_html_e('Per page', 'modern-job-board'); ?></span>
                        <button class="mjb-jobs-per-btn mjb-js-popup" id="mjb-jobs-per-btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
                            /* translators: %d: current per-page size */
                            __('Jobs per page, currently %d', 'modern-job-board'),
                            (int) $state['per']
                        )); ?>">
                            <span id="mjb-jobs-per-value"><?php echo esc_html((string) $state['per']); ?></span>
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                            echo MJB_Icons::render('chevron-down', 14);
                            ?>
                        </button>
                        <div class="mjb-jobs-menu mjb-jobs-per-menu" id="mjb-jobs-per-menu" role="menu" aria-labelledby="mjb-jobs-per-label" hidden>
                            <?php foreach (self::per_page_options() as $n) : ?>
                            <button type="button" role="menuitemradio" aria-checked="<?php echo $n === (int) $state['per'] ? 'true' : 'false'; ?>" data-per="<?php echo esc_attr((string) $n); ?>"><?php echo esc_html((string) $n); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php self::render_pager($data['page'], $data['pages']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mjb-jobs-bulkbar" id="mjb-jobs-bulkbar" role="region" aria-label="<?php esc_attr_e('Bulk actions', 'modern-job-board'); ?>">
                <span class="mjb-jobs-bulkbar__n" id="mjb-jobs-bulk-count"><?php esc_html_e('0 selected', 'modern-job-board'); ?></span>
                <span class="mjb-jobs-bulkbar__rule" aria-hidden="true"></span>
                <button type="button" data-bulk="extend"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('calendar', 15);
                    esc_html_e('Extend expiry', 'modern-job-board');
                ?></button>
                <button type="button" data-bulk="fill"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('circle-check', 15);
                    esc_html_e('Mark filled', 'modern-job-board');
                ?></button>
                <button type="button" data-bulk="feature"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('star', 15);
                    esc_html_e('Feature', 'modern-job-board');
                ?></button>
                <button type="button" data-bulk="export"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('download', 15);
                    esc_html_e('Export', 'modern-job-board');
                ?></button>
                <span class="mjb-jobs-bulkbar__rule" aria-hidden="true"></span>
                <button type="button" class="is-danger" data-bulk="delete"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('trash-2', 15);
                    esc_html_e('Delete', 'modern-job-board');
                ?></button>
                <button type="button" class="mjb-jobs-bulkbar__close" id="mjb-jobs-bulk-clear" aria-label="<?php esc_attr_e('Clear selection', 'modern-job-board'); ?>">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('x', 16);
                    ?>
                </button>
            </div>

            <?php MJB_Admin_Tabs::render_delete_job_modal(); ?>
        </div>
        <?php
    }

    /**
     * Empty views stay hidden so the tablist only shows lists that have items.
     * `all` and the active view stay visible so the empty state still has a tab.
     *
     * @param string $id
     * @param mixed  $count
     * @param string $current
     * @return bool
     */
    public static function should_show_view_pill($id, $count, $current)
    {
        $id = sanitize_key((string) $id);
        if ($id === 'all' || $id === (string) $current) {
            return true;
        }

        return (int) $count > 0;
    }

    /**
     * @param string $id
     * @param string $label
     * @param int    $count
     * @param string $current
     * @param bool   $warn
     * @return void
     */
    private static function render_view_pill($id, $label, $count, $current, $warn)
    {
        if (!self::should_show_view_pill($id, $count, $current)) {
            return;
        }
        $selected = $id === $current;
        $open = $selected
            ? '<span class="mjb-view-pill" role="tab" data-view="' . esc_attr($id) . '" aria-selected="true" tabindex="0">'
            : '<button class="mjb-view-pill" type="button" role="tab" data-view="' . esc_attr($id) . '" aria-selected="false">';
        $close = $selected ? '</span>' : '</button>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tags are literals; attrs escaped above.
        echo $open;
        echo esc_html($label);
        echo '<span class="mjb-view-pill__count' . ($warn && !$selected ? ' is-warn' : '') . '">' . esc_html((string) (int) $count) . '</span>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Literal span or button closer.
        echo $close;
    }

    /**
     * @param string                          $key
     * @param string                          $label
     * @param array<int, array<string,mixed>> $options
     * @param string                          $current
     * @return void
     */
    private static function render_filter_menu($key, $label, $options, $current)
    {
        $current_label = __('All', 'modern-job-board');
        foreach ($options as $opt) {
            if ((string) $opt['value'] === (string) $current) {
                $current_label = $opt['label'];
                break;
            }
        }
        ?>
        <div class="mjb-jobs-filter" data-filter="<?php echo esc_attr($key); ?>">
            <button class="mjb-jobs-filter__btn mjb-js-popup" type="button" aria-haspopup="menu" aria-expanded="false">
                <?php echo esc_html($label); ?> <b><?php echo esc_html($current_label); ?></b>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                echo MJB_Icons::render('chevron-down', 14);
                ?>
            </button>
            <div class="mjb-jobs-menu" role="menu" hidden>
                <button type="button" role="menuitemradio" data-value="" aria-checked="<?php echo $current === '' || $current === '0' ? 'true' : 'false'; ?>"><?php esc_html_e('All', 'modern-job-board'); ?></button>
                <?php foreach ($options as $opt) : ?>
                <button type="button" role="menuitemradio" data-value="<?php echo esc_attr((string) $opt['value']); ?>" aria-checked="<?php echo (string) $opt['value'] === (string) $current ? 'true' : 'false'; ?>"><?php echo esc_html($opt['label']); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param string $key
     * @param string $type
     * @param string $class
     * @param string $label
     * @param string $sort
     * @return void
     */
    private static function render_sort_th($key, $type, $class, $label, $sort)
    {
        ?>
        <th class="<?php echo esc_attr($class); ?>" scope="col" aria-sort="<?php echo esc_attr($sort); ?>" data-key="<?php echo esc_attr($key); ?>" data-type="<?php echo esc_attr($type); ?>">
            <button type="button"><?php echo esc_html($label); ?>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                echo MJB_Icons::render('chevron-down', 13);
                ?>
            </button>
        </th>
        <?php
    }

    /**
     * @param array<string, mixed> $row
     * @return void
     */
    private static function render_row($row)
    {
        $title = (string) $row['title'];
        $edit = (string) $row['edit_url'];
        $flags = array();
        if (!empty($row['needs_attention'])) {
            $flags[] = 'attention';
        }
        if (!empty($row['expiring'])) {
            $flags[] = 'expiring';
        }
        if ((int) $row['views'] === 0 && in_array($row['status'], array('publish', 'expired'), true)) {
            $flags[] = 'noviews';
        }
        ?>
        <tr data-status="<?php echo esc_attr($row['list_status']); ?>"
            data-flags="<?php echo esc_attr(implode(' ', $flags)); ?>"
            data-job-id="<?php echo esc_attr((string) $row['id']); ?>"
            data-featured="<?php echo !empty($row['featured']) ? '1' : '0'; ?>"
            data-external="<?php echo esc_attr((string) $row['external_url']); ?>"
            data-view-url="<?php echo esc_url($row['view_url']); ?>">
            <td class="mjb-jobs-col-check">
                <input type="checkbox" aria-label="<?php echo esc_attr(sprintf(
                    /* translators: %s: job title */
                    __('Select %s', 'modern-job-board'),
                    $title
                )); ?>">
            </td>
            <td class="mjb-jobs-col-job" data-sort="<?php echo esc_attr(strtolower($title)); ?>">
                <?php if ($edit) : ?>
                <a class="mjb-jobs-title" href="<?php echo esc_url($edit); ?>"><span class="mjb-u"><?php echo esc_html($title); ?></span></a>
                <?php else : ?>
                <span class="mjb-jobs-title"><?php echo esc_html($title); ?></span>
                <?php endif; ?>
                <?php if ($row['company'] !== '') : ?>
                <div class="mjb-jobs-meta"><span class="mjb-jobs-meta__co"><?php echo esc_html($row['company']); ?></span></div>
                <?php endif; ?>
                <div class="mjb-jobs-flags">
                    <?php if ($row['location'] !== '') : ?>
                    <span class="mjb-chip mjb-chip--loc"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('map-pin', 13);
                        echo esc_html($row['location']);
                    ?></span>
                    <?php endif; ?>
                    <?php if ($row['type'] !== '') : ?>
                    <span class="mjb-chip"><?php echo esc_html($row['type']); ?></span>
                    <?php else : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php esc_html_e('No job type', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                    <?php if ($row['category'] === '' && $row['status'] === 'draft') : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php esc_html_e('No category', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['employer_submitted'])) : ?>
                    <span class="mjb-chip mjb-chip--line mjb-chip--flag"><?php esc_html_e('Submitted by employer', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['featured'])) : ?>
                    <span class="mjb-chip mjb-chip--solid mjb-chip--flag"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('star', 12);
                        esc_html_e('Featured', 'modern-job-board');
                    ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['most_viewed'])) : ?>
                    <span class="mjb-chip mjb-chip--hot mjb-chip--flag"><?php esc_html_e('Most viewed listing', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['external'])) : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('external-link', 12);
                        esc_html_e('Applies off-site', 'modern-job-board');
                    ?></span>
                    <?php endif; ?>
                    <?php if ((int) $row['views'] === 0 && in_array($row['status'], array('publish', 'expired'), true)) : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php esc_html_e('No views since posted', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-status" data-sort="<?php echo esc_attr($row['list_status']); ?>">
                <?php self::render_status_chip($row); ?>
                <?php if ($row['status_sub'] !== '') : ?>
                <span class="mjb-jobs-expiry<?php echo !empty($row['expiry_class']) ? ' ' . esc_attr($row['expiry_class']) : ''; ?>"><?php echo esc_html($row['status_sub']); ?></span>
                <?php endif; ?>
            </td>
            <td class="mjb-jobs-col-num" data-label="<?php esc_attr_e('Applications', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['applications']); ?>">
                <div class="mjb-jobs-metric">
                    <?php if (!empty($row['external'])) : ?>
                    <span class="mjb-jobs-metric__val is-zero"><?php esc_html_e('n/a', 'modern-job-board'); ?></span>
                    <span class="mjb-jobs-metric__sub"><?php esc_html_e('not tracked', 'modern-job-board'); ?></span>
                    <?php else : ?>
                    <span class="mjb-jobs-metric__val<?php echo (int) $row['applications'] === 0 ? ' is-zero' : ''; ?>"><?php echo esc_html(number_format_i18n((int) $row['applications'])); ?></span>
                    <?php if ($row['apps_sub'] !== '') : ?>
                    <span class="mjb-jobs-metric__sub"><?php echo esc_html($row['apps_sub']); ?></span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-num" data-label="<?php esc_attr_e('Views', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['views']); ?>">
                <div class="mjb-jobs-metric">
                    <span class="mjb-jobs-metric__val<?php echo (int) $row['views'] === 0 ? ' is-zero' : ''; ?>"><?php echo esc_html(number_format_i18n((int) $row['views'])); ?></span>
                    <?php if ($row['views_sub'] !== '') : ?>
                    <span class="mjb-jobs-metric__sub"><?php echo esc_html($row['views_sub']); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-date" data-label="<?php esc_attr_e('Posted', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['posted_ymd']); ?>">
                <div class="mjb-jobs-date"><?php echo esc_html($row['posted_label']); ?><span><?php echo esc_html($row['posted_rel']); ?></span></div>
            </td>
            <td class="mjb-jobs-col-act">
                <div class="mjb-jobs-actions">
                    <?php self::render_primary_actions($row); ?>
                    <button type="button" class="mjb-job-delete" hidden data-job-id="<?php echo esc_attr((string) $row['id']); ?>" data-job-title="<?php echo esc_attr($title); ?>"><?php esc_html_e('Delete', 'modern-job-board'); ?></button>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * @param array<string, mixed> $row
     * @return void
     */
    private static function render_status_chip($row)
    {
        $status = $row['list_status'];
        if ($status === 'pending') {
            echo '<span class="mjb-chip mjb-chip--warn">' . esc_html__('Pending review', 'modern-job-board') . '</span>';
            return;
        }
        if ($status === 'draft') {
            echo '<span class="mjb-chip mjb-chip--muted">' . esc_html__('Draft', 'modern-job-board') . '</span>';
            return;
        }
        if ($status === 'expired') {
            echo '<span class="mjb-chip mjb-chip--crit">' . esc_html__('Expired', 'modern-job-board') . '</span>';
            return;
        }
        if ($status === 'filled') {
            echo '<span class="mjb-chip mjb-chip--muted">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render('circle-check', 12);
            echo esc_html__('Filled', 'modern-job-board');
            echo '</span>';
            return;
        }
        echo '<span class="mjb-chip">' . esc_html__('Published', 'modern-job-board') . '</span>';
    }

    /**
     * @param array<string, mixed> $row
     * @return void
     */
    private static function render_primary_actions($row)
    {
        $title = (string) $row['title'];
        $status = $row['list_status'];

        if ($status === 'pending') {
            self::render_act_btn('approve', 'circle-check', sprintf(__('Approve and publish %s', 'modern-job-board'), $title), __('Approve and publish', 'modern-job-board'));
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
        } elseif ($status === 'draft') {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
            self::render_act_link($row['view_url'], 'eye', sprintf(__('Preview %s', 'modern-job-board'), $title), __('Preview', 'modern-job-board'), true);
        } elseif ($status === 'expired') {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
            self::render_act_btn('republish', 'refresh-cw', sprintf(__('Republish %s', 'modern-job-board'), $title), __('Republish for another period', 'modern-job-board'));
        } elseif ($status === 'filled') {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
            self::render_act_btn('duplicate', 'copy', sprintf(__('Duplicate %s as a new listing', 'modern-job-board'), $title), __('Duplicate as a new listing', 'modern-job-board'));
        } elseif (!empty($row['expiring'])) {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
            self::render_act_btn('extend', 'calendar', sprintf(__('Extend expiry for %s', 'modern-job-board'), $title), sprintf(
                /* translators: %d: days added */
                __('Extend expiry by %d days', 'modern-job-board'),
                self::listing_duration_days()
            ));
        } elseif (!empty($row['external_url'])) {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
            self::render_act_link($row['external_url'], 'external-link', sprintf(__('Open the external apply URL for %s', 'modern-job-board'), $title), __('Open the external apply URL', 'modern-job-board'), true);
        } else {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit job', 'modern-job-board'));
            self::render_act_link($row['apps_url'], 'inbox', sprintf(__('View applications for %s', 'modern-job-board'), $title), __('View applications', 'modern-job-board'), false, 'applications', (int) $row['id']);
        }

        ?>
        <button class="mjb-jobs-act mjb-js-popup" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
            /* translators: %s: job title */
            __('More actions for %s', 'modern-job-board'),
            $title
        )); ?>">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render('ellipsis', 16);
            ?>
        </button>
        <?php
    }

    /**
     * @param string $action
     * @param string $icon
     * @param string $aria
     * @param string $tip
     * @return void
     */
    private static function render_act_btn($action, $icon, $aria, $tip)
    {
        ?>
        <button class="mjb-jobs-act" type="button" data-action="<?php echo esc_attr($action); ?>" data-tip="<?php echo esc_attr($tip); ?>" aria-label="<?php echo esc_attr($aria); ?>">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render($icon, 16);
            ?>
        </button>
        <?php
    }

    /**
     * @param string $url
     * @param string $icon
     * @param string $aria
     * @param string $tip
     * @param bool   $blank
     * @param string $tab
     * @param int    $job_id
     * @return void
     */
    private static function render_act_link($url, $icon, $aria, $tip, $blank = false, $tab = '', $job_id = 0)
    {
        if ($url === '') {
            return;
        }
        $class = 'mjb-jobs-act';
        $data = '';
        if ($tab !== '') {
            $class .= ' mjb-js-tab';
            $data .= ' data-tab="' . esc_attr($tab) . '"';
        }
        if ((int) $job_id > 0) {
            $data .= ' data-job="' . esc_attr((string) (int) $job_id) . '"';
        }
        ?>
        <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>" data-tip="<?php echo esc_attr($tip); ?>" aria-label="<?php echo esc_attr($aria); ?>"<?php
            echo $blank ? ' target="_blank" rel="noopener noreferrer"' : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_attr above.
            echo $data;
        ?>>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render($icon, 16);
            ?>
        </a>
        <?php
    }

    /**
     * @param int $page
     * @param int $pages
     * @return void
     */
    private static function render_pager($page, $pages)
    {
        if ($pages <= 1) {
            return;
        }
        echo '<nav class="mjb-jobs-pager" aria-label="' . esc_attr__('Job pages', 'modern-job-board') . '">';
        $window = array();
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === 1 || $i === $pages || abs($i - $page) <= 1) {
                $window[] = $i;
            }
        }
        $prev = 0;
        foreach ($window as $i) {
            if ($prev && $i > $prev + 1) {
                echo '<span class="mjb-jobs-pager__gap">…</span>';
            }
            if ($i === $page) {
                echo '<span class="mjb-jobs-pager__cur" aria-current="page">' . esc_html((string) $i) . '</span>';
            } else {
                printf(
                    '<button type="button" class="mjb-admin-pagination__btn" data-page="%1$s">%2$s</button>',
                    esc_attr((string) $i),
                    esc_html((string) $i)
                );
            }
            $prev = $i;
        }
        if ($page < $pages) {
            printf(
                '<button type="button" class="mjb-admin-pagination__btn" data-page="%1$s" aria-label="%2$s">%3$s</button>',
                esc_attr((string) ($page + 1)),
                esc_attr__('Next page', 'modern-job-board'),
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                MJB_Icons::render('chevron-right', 15)
            );
        }
        echo '</nav>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function catalog()
    {
        if (is_array(self::$catalog)) {
            return self::$catalog;
        }

        $ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));

        $ids = array_map('intval', (array) $ids);
        $app_counts = array();
        if (class_exists('MJB_Dashboard') && $ids) {
            $app_counts = MJB_Dashboard::get_application_counts_for_jobs($ids);
        }

        $now = (int) current_time('timestamp');
        $until = $now + (7 * DAY_IN_SECONDS);
        $max_views = 0;
        $rows = array();

        foreach ($ids as $job_id) {
            $row = self::build_row($job_id, $app_counts, $now, $until);
            if ((int) $row['views'] > $max_views) {
                $max_views = (int) $row['views'];
            }
            $rows[] = $row;
        }

        if ($max_views > 0) {
            foreach ($rows as &$row) {
                $row['most_viewed'] = ((int) $row['views'] === $max_views);
            }
            unset($row);
        }

        self::$catalog = $rows;

        return self::$catalog;
    }

    /**
     * Reset request-local catalog (tests).
     *
     * @return void
     */
    public static function reset_catalog()
    {
        self::$catalog = null;
    }

    /**
     * @param int                  $job_id
     * @param array<int,int>       $app_counts
     * @param int                  $now
     * @param int                  $until
     * @return array<string, mixed>
     */
    public static function build_row($job_id, $app_counts, $now, $until)
    {
        $job_id = (int) $job_id;
        $status = (string) get_post_status($job_id);
        $filled = class_exists('MJB_Job_Ops') ? MJB_Job_Ops::is_filled($job_id) : ((string) get_post_meta($job_id, '_job_filled', true) === '1');
        $featured = (int) get_post_meta($job_id, '_featured', true) === 1;
        $method = (string) get_post_meta($job_id, '_application_method', true);
        $external_url = (string) get_post_meta($job_id, '_application_url', true);
        $external = $method === 'external' || $external_url !== '';
        $views = class_exists('MJB_Analytics')
            ? (int) get_post_meta($job_id, MJB_Analytics::VIEW_COUNT_META, true)
            : (int) get_post_meta($job_id, '_mjb_view_count', true);
        $apps = isset($app_counts[$job_id]) ? (int) $app_counts[$job_id] : 0;

        $company_id = (int) get_post_meta($job_id, '_company_id', true);
        $company = $company_id ? (string) get_the_title($company_id) : (string) get_post_meta($job_id, '_company_name', true);
        $location = class_exists('MJB_Location') ? (string) MJB_Location::format_job_location($job_id) : '';

        $type_names = self::term_names($job_id, 'job_type');
        $cat_names = self::term_names($job_id, 'job_category');
        $type = $type_names ? $type_names[0] : '';
        $category = $cat_names ? $cat_names[0] : '';

        $expires = (string) get_post_meta($job_id, '_job_expires', true);
        $expires_ts = $expires !== '' ? strtotime($expires) : 0;
        $expiring = $status === 'publish' && !$filled && $expires_ts && $expires_ts >= $now && $expires_ts <= $until;

        $posted_raw = get_the_date('U', $job_id);
        $posted_ts = is_numeric($posted_raw) ? (int) $posted_raw : strtotime((string) $posted_raw);
        if (!$posted_ts) {
            $posted_ts = $now;
        }

        $list_status = $status;
        if ($filled) {
            $list_status = 'filled';
        }

        $missing_type = $type === '';
        $missing_cat = $category === '';
        $employer_submitted = $status === 'pending';
        $needs_attention = $status === 'pending'
            || $status === 'expired'
            || $expiring
            || ($status === 'draft' && ($missing_type || $missing_cat))
            || ($status === 'publish' && $views === 0);

        $status_sub = '';
        $expiry_class = '';
        if ($status === 'pending') {
            $status_sub = sprintf(
                /* translators: 1: date, 2: relative time */
                __('Submitted %1$s · %2$s', 'modern-job-board'),
                self::format_day($posted_ts),
                self::relative_time($posted_ts, $now)
            );
            $expiry_class = 'is-soon';
        } elseif ($status === 'draft') {
            $status_sub = sprintf(
                /* translators: 1: date, 2: relative time */
                __('Saved %1$s · %2$s', 'modern-job-board'),
                self::format_day($posted_ts),
                self::relative_time($posted_ts, $now)
            );
        } elseif ($list_status === 'filled') {
            $close_ts = $expires_ts ? $expires_ts : $posted_ts;
            $status_sub = sprintf(
                /* translators: 1: date, 2: relative time */
                __('Closed %1$s · %2$s', 'modern-job-board'),
                self::format_day($close_ts),
                self::relative_time($close_ts, $now)
            );
        } elseif ($status === 'expired' || ($expires_ts && $expires_ts < $now && $status === 'publish')) {
            $when = $expires_ts ? $expires_ts : $posted_ts;
            $status_sub = sprintf(
                /* translators: 1: date, 2: relative time */
                __('Expired %1$s · %2$s', 'modern-job-board'),
                self::format_day($when),
                self::relative_time($when, $now)
            );
            $expiry_class = 'is-gone';
        } elseif ($expires_ts) {
            $status_sub = sprintf(
                /* translators: 1: date, 2: relative remaining */
                __('Expires %1$s · %2$s', 'modern-job-board'),
                self::format_day($expires_ts),
                self::relative_future($expires_ts, $now)
            );
            if ($expiring) {
                $expiry_class = 'is-soon';
            }
        }

        $views_sub = '';
        if ($status === 'draft' || $status === 'pending') {
            $views_sub = __('not public yet', 'modern-job-board');
        } elseif ($views === 0 && in_array($status, array('publish', 'expired'), true)) {
            $views_sub = __('never viewed', 'modern-job-board');
        }

        $apps_sub = '';
        if ($external) {
            $apps_sub = __('not tracked', 'modern-job-board');
        } elseif ($filled) {
            $apps_sub = __('filled', 'modern-job-board');
        } elseif ($status === 'publish') {
            $pct = $views > 0 ? (int) round(($apps / $views) * 100) : 0;
            $apps_sub = sprintf(
                /* translators: %d: conversion percent */
                __('%d%% of views', 'modern-job-board'),
                $pct
            );
        }

        $apps_url = class_exists('MJB_Admin_Tabs')
            ? MJB_Admin_Tabs::get_tab_url('applications', array('mjb_job' => $job_id))
            : admin_url('edit.php?post_type=job_application');

        return array(
            'id' => $job_id,
            'title' => (string) get_the_title($job_id),
            'status' => $status,
            'list_status' => $list_status,
            'filled' => $filled,
            'featured' => $featured,
            'external' => $external,
            'external_url' => $external_url,
            'views' => $views,
            'applications' => $apps,
            'company_id' => $company_id,
            'company' => $company,
            'location' => $location,
            'type' => $type,
            'type_slug' => $type !== '' ? sanitize_title($type) : '',
            'category' => $category,
            'category_slug' => $category !== '' ? sanitize_title($category) : '',
            'expires' => $expires,
            'expires_ts' => $expires_ts ? $expires_ts : 0,
            'expiring' => $expiring,
            'posted_ts' => $posted_ts,
            'posted_ymd' => (int) gmdate('Ymd', $posted_ts),
            'posted_label' => self::format_day($posted_ts),
            'posted_rel' => self::relative_time($posted_ts, $now),
            'status_sub' => $status_sub,
            'expiry_class' => $expiry_class,
            'views_sub' => $views_sub,
            'apps_sub' => $apps_sub,
            'needs_attention' => $needs_attention,
            'employer_submitted' => $employer_submitted,
            'most_viewed' => false,
            'edit_url' => (string) get_edit_post_link($job_id, 'raw'),
            'view_url' => (string) get_permalink($job_id),
            'apps_url' => $apps_url,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, int>
     */
    private static function view_counts($catalog)
    {
        $counts = array(
            'all' => 0,
            'attention' => 0,
            'expiring' => 0,
            'zero_views' => 0,
            'no_apps' => 0,
            'published' => 0,
            'pending' => 0,
            'draft' => 0,
            'expired' => 0,
            'filled' => 0,
        );
        foreach ($catalog as $row) {
            $counts['all']++;
            foreach (self::view_ids() as $view) {
                if ($view === 'all') {
                    continue;
                }
                if (self::row_matches_view($row, $view)) {
                    $counts[$view]++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, array<int, array<string, string>>>
     */
    private static function filter_options($catalog)
    {
        $companies = array();
        $categories = array();
        $types = array();
        foreach ($catalog as $row) {
            if ((int) $row['company_id'] > 0 && $row['company'] !== '') {
                $companies[(int) $row['company_id']] = array(
                    'value' => (string) (int) $row['company_id'],
                    'label' => $row['company'],
                );
            }
            if ($row['category_slug'] !== '') {
                $categories[$row['category_slug']] = array(
                    'value' => $row['category_slug'],
                    'label' => $row['category'],
                );
            }
            if ($row['type_slug'] !== '') {
                $types[$row['type_slug']] = array(
                    'value' => $row['type_slug'],
                    'label' => $row['type'],
                );
            }
        }
        $sort_label = static function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        };
        $companies = array_values($companies);
        $categories = array_values($categories);
        $types = array_values($types);
        usort($companies, $sort_label);
        usort($categories, $sort_label);
        usort($types, $sort_label);

        return array(
            'companies' => $companies,
            'categories' => $categories,
            'types' => $types,
        );
    }

    /**
     * Zero-application notice, only when the board has live jobs and no apps.
     *
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, mixed>|null
     */
    private static function apps_notice($catalog)
    {
        $user_id = get_current_user_id();
        if ($user_id && get_user_meta($user_id, self::USER_NOTICE_META, true) === '1') {
            return null;
        }

        $published = 0;
        $views = 0;
        $apps = 0;
        $external = 0;
        foreach ($catalog as $row) {
            if ($row['status'] !== 'publish') {
                continue;
            }
            $published++;
            $views += (int) $row['views'];
            $apps += (int) $row['applications'];
            if (!empty($row['external'])) {
                $external++;
            }
        }

        if ($published === 0 || $apps > 0) {
            return null;
        }

        $body = sprintf(
            /* translators: 1: published jobs, 2: views */
            __('There are %1$d published jobs and %2$d views, but no applications yet. Confirm the apply form is on job pages, and check listings that send candidates off-site.', 'modern-job-board'),
            $published,
            $views
        );
        if ($external > 0) {
            $body = sprintf(
                /* translators: 1: published jobs, 2: views, 3: external-apply count */
                __('There are %1$d published jobs and %2$d views, but no applications yet. %3$d listing(s) send candidates off-site, so those applications are not tracked here.', 'modern-job-board'),
                $published,
                $views,
                $external
            );
        }

        return array(
            'title' => __('Not one listing has received an application', 'modern-job-board'),
            'body' => $body,
            'action' => __('Open listing settings', 'modern-job-board'),
            'url' => MJB_Admin_Tabs::get_tab_url('settings', array('settings_tab' => 'listing')),
            'tab' => 'settings',
            'tab_args' => array('settings_tab' => 'listing'),
        );
    }

    /**
     * @param int    $post_id
     * @param string $taxonomy
     * @return string[]
     */
    private static function term_names($post_id, $taxonomy)
    {
        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
        if (is_wp_error($terms) || !is_array($terms)) {
            return array();
        }
        $out = array();
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->name)) {
                $out[] = (string) $term->name;
            } elseif (is_string($term) && $term !== '') {
                $out[] = $term;
            }
        }

        return $out;
    }

    /**
     * @param int $ts
     * @return string
     */
    private static function format_day($ts)
    {
        $ts = (int) $ts;
        if (function_exists('date_i18n')) {
            return date_i18n('j M', $ts);
        }

        return gmdate('j M', $ts);
    }

    /**
     * @param int $ts
     * @param int $now
     * @return string
     */
    private static function relative_time($ts, $now)
    {
        $diff = max(0, (int) $now - (int) $ts);
        if (function_exists('human_time_diff')) {
            return sprintf(
                /* translators: %s: relative time */
                __('%s ago', 'modern-job-board'),
                human_time_diff($ts, $now)
            );
        }

        return self::diff_label($diff, true);
    }

    /**
     * @param int $ts
     * @param int $now
     * @return string
     */
    private static function relative_future($ts, $now)
    {
        $diff = max(0, (int) $ts - (int) $now);

        return self::diff_label($diff, false);
    }

    /**
     * @param int  $diff
     * @param bool $ago
     * @return string
     */
    private static function diff_label($diff, $ago)
    {
        if ($diff < HOUR_IN_SECONDS) {
            $n = max(1, (int) round($diff / MINUTE_IN_SECONDS));
            $label = sprintf(_n('%d minute', '%d minutes', $n, 'modern-job-board'), $n);
        } elseif ($diff < DAY_IN_SECONDS) {
            $n = max(1, (int) round($diff / HOUR_IN_SECONDS));
            $label = sprintf(_n('%d hour', '%d hours', $n, 'modern-job-board'), $n);
        } elseif ($diff < WEEK_IN_SECONDS) {
            $n = max(1, (int) round($diff / DAY_IN_SECONDS));
            $label = sprintf(_n('%d day', '%d days', $n, 'modern-job-board'), $n);
        } elseif ($diff < (DAY_IN_SECONDS * 45)) {
            $n = max(1, (int) round($diff / WEEK_IN_SECONDS));
            $label = sprintf(_n('%d week', '%d weeks', $n, 'modern-job-board'), $n);
        } else {
            $n = max(1, (int) round($diff / (DAY_IN_SECONDS * 30)));
            $label = sprintf(_n('%d month', '%d months', $n, 'modern-job-board'), $n);
        }

        if ($ago) {
            return sprintf(
                /* translators: %s: relative time */
                __('%s ago', 'modern-job-board'),
                $label
            );
        }

        return sprintf(
            /* translators: %s: relative time */
            __('in %s', 'modern-job-board'),
            $label
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param int                  $total
     * @return string
     */
    private static function result_meta_text($state, $total)
    {
        $sort = __('sorted by newest', 'modern-job-board');
        if ($state['orderby'] === 'title') {
            $sort = __('sorted by title', 'modern-job-board');
        } elseif ($state['orderby'] === 'views') {
            $sort = __('sorted by views', 'modern-job-board');
        } elseif ($state['orderby'] === 'apps') {
            $sort = __('sorted by applications', 'modern-job-board');
        } elseif ($state['orderby'] === 'status') {
            $sort = __('sorted by status', 'modern-job-board');
        }

        if ($state['q'] !== '') {
            return sprintf(
                /* translators: %d: match count */
                _n('Search results · %d job', 'Search results · %d jobs', $total, 'modern-job-board'),
                $total
            );
        }

        if ($state['view'] !== 'all') {
            return sprintf(
                /* translators: 1: count, 2: sort label */
                _n('%1$d job in view · %2$s', '%1$d jobs in view · %2$s', $total, 'modern-job-board'),
                $total,
                $sort
            );
        }

        return sprintf(
            /* translators: 1: count, 2: sort label */
            __('%1$d jobs · %2$s', 'modern-job-board'),
            $total,
            $sort
        );
    }

    /**
     * @param string               $url
     * @param array<string, mixed> $state
     * @return string
     */
    private static function add_state_args($url, $state)
    {
        $args = array();
        if ($state['view'] !== 'all') {
            $args['mjb_list'] = $state['view'];
        }
        if ($state['q'] !== '') {
            $args['mjb_q'] = $state['q'];
        }
        if ((int) $state['company'] > 0) {
            $args['mjb_company'] = (int) $state['company'];
        }
        if ($state['category'] !== '') {
            $args['mjb_cat'] = $state['category'];
        }
        if ($state['type'] !== '') {
            $args['mjb_type'] = $state['type'];
        }

        return $args ? add_query_arg($args, $url) : $url;
    }

    /**
     * @param string $action
     * @param int    $count
     * @return string
     */
    private static function action_message($action, $count)
    {
        switch ($action) {
            case 'extend':
                return sprintf(_n('Extended %d job.', 'Extended %d jobs.', $count, 'modern-job-board'), $count);
            case 'feature':
                return sprintf(_n('Featured %d job.', 'Featured %d jobs.', $count, 'modern-job-board'), $count);
            case 'fill':
                return sprintf(_n('Marked %d job filled.', 'Marked %d jobs filled.', $count, 'modern-job-board'), $count);
            case 'unfill':
                return sprintf(_n('Reopened %d job.', 'Reopened %d jobs.', $count, 'modern-job-board'), $count);
            case 'publish':
            case 'approve':
                return sprintf(_n('Published %d job.', 'Published %d jobs.', $count, 'modern-job-board'), $count);
            case 'republish':
                return sprintf(_n('Republished %d job.', 'Republished %d jobs.', $count, 'modern-job-board'), $count);
            case 'duplicate':
                return __('Duplicated as a draft.', 'modern-job-board');
            case 'delete':
                return sprintf(_n('Moved %d job to trash.', 'Moved %d jobs to trash.', $count, 'modern-job-board'), $count);
            default:
                return sprintf(_n('Updated %d job.', 'Updated %d jobs.', $count, 'modern-job-board'), $count);
        }
    }
}
