<?php
/**
 * Admin Applications tab: saved views, search, filters, row and bulk actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Applications
{
    const PER_PAGE_DEFAULT = 20;
    const USER_PER_PAGE_META = 'mjb_applications_per_page';

    /** @var array<int, array<string, mixed>>|null */
    private static $catalog = null;

    /**
     * Hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_ajax_mjb_admin_application_action', array(__CLASS__, 'ajax_action'));
        add_action('wp_ajax_mjb_admin_applications_suggest', array(__CLASS__, 'ajax_suggest'));
        add_action('admin_post_mjb_export_applications', array(__CLASS__, 'handle_export'));
    }

    /**
     * @return int[]
     */
    public static function per_page_options()
    {
        return array(5, 10, 20, 50, 100);
    }

    /**
     * @return string[]
     */
    public static function view_ids()
    {
        return array(
            'all',
            'new',
            'no_resume',
            'reviewed',
            'shortlisted',
            'rejected',
            'hired',
        );
    }

    /**
     * @param string $view
     * @return string
     */
    public static function sanitize_view($view)
    {
        $view = sanitize_key((string) $view);

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
        $allowed = array('title', 'status', 'job', 'posted');

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
     * @param mixed $action
     * @return string
     */
    public static function sanitize_action($action)
    {
        $action = sanitize_key((string) $action);
        $allowed = array('reviewed', 'shortlisted', 'rejected', 'hired', 'new', 'delete');

        return in_array($action, $allowed, true) ? $action : '';
    }

    /**
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

        $job = 0;
        if (isset($_REQUEST['mjb_job'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $job = max(0, (int) $_REQUEST['mjb_job']);
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
        if (isset($_REQUEST['mjb_per']) && (string) wp_unslash($_REQUEST['mjb_per']) !== '') {
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
            'job' => $job,
            'orderby' => $orderby,
            'order' => $order,
            'per' => $per,
            'page' => max(1, (int) $page),
        );
    }

    /**
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
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $state
     * @return bool
     */
    public static function row_matches_state($row, $state)
    {
        if (!self::row_matches_view($row, $state['view'])) {
            return false;
        }

        if ((int) $state['job'] > 0 && (int) $row['job_id'] !== (int) $state['job']) {
            return false;
        }

        $q = strtolower(trim((string) $state['q']));
        if ($q === '') {
            return true;
        }

        $hay = strtolower(
            $row['candidate'] . ' ' . $row['email'] . ' ' . $row['job_title'] . ' ' . $row['company']
        );

        return strpos($hay, $q) !== false;
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
            case 'new':
                return $row['status'] === 'new';
            case 'no_resume':
                return empty($row['has_resume']);
            case 'reviewed':
            case 'shortlisted':
            case 'rejected':
            case 'hired':
                return $row['status'] === $view;
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
            'new' => 0,
            'reviewed' => 1,
            'shortlisted' => 2,
            'hired' => 3,
            'rejected' => 4,
        );

        usort($rows, static function ($a, $b) use ($orderby, $dir, $status_rank) {
            if ($orderby === 'title') {
                return strcasecmp((string) $a['candidate'], (string) $b['candidate']) * $dir;
            }
            if ($orderby === 'status') {
                $as = isset($status_rank[$a['status']]) ? $status_rank[$a['status']] : 9;
                $bs = isset($status_rank[$b['status']]) ? $status_rank[$b['status']] : 9;

                return ($as <=> $bs) * $dir;
            }
            if ($orderby === 'job') {
                return strcasecmp((string) $a['job_title'], (string) $b['job_title']) * $dir;
            }

            return (((int) $a['posted_ts']) <=> ((int) $b['posted_ts'])) * $dir;
        });

        return $rows;
    }

    /**
     * Predictive suggestions: candidate, email, job title.
     *
     * @param string $q
     * @param int    $limit
     * @return array<int, array{value:string,label:string,kind:string,hint:string}>
     */
    public static function suggest($q, $limit = 12)
    {
        $q = strtolower(trim((string) $q));
        $limit = max(1, min(25, (int) $limit));
        $rank = array(__CLASS__, 'match_rank');

        $names = array();
        $emails = array();
        $jobs = array();
        $seen_names = array();
        $seen_emails = array();
        $seen_jobs = array();

        foreach (self::catalog() as $row) {
            $name = (string) $row['candidate'];
            $email = (string) $row['email'];
            $job_title = (string) $row['job_title'];
            $posted = (int) $row['posted_ts'];

            $name_rank = call_user_func($rank, $name, $q);
            $email_rank = call_user_func($rank, $email, $q);
            $job_rank = call_user_func($rank, $job_title, $q);
            $app_rank = $q === '' ? $name_rank : max($name_rank, $email_rank, $job_rank);

            if ($app_rank > 0 && $name !== '') {
                $key = strtolower($name);
                if (!isset($seen_names[$key])) {
                    $seen_names[$key] = true;
                    $hint_parts = array();
                    if ($job_title !== '') {
                        $hint_parts[] = $job_title;
                    }
                    $names[] = array(
                        'value' => $name,
                        'label' => $name,
                        'kind' => 'candidate',
                        'hint' => implode(' · ', $hint_parts),
                        'rank' => $app_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q !== '' && $email_rank > 0 && $email !== '') {
                $key = strtolower($email);
                if (!isset($seen_emails[$key])) {
                    $seen_emails[$key] = true;
                    $emails[] = array(
                        'value' => $email,
                        'label' => $email,
                        'kind' => 'email',
                        'hint' => __('Email', 'modern-job-board'),
                        'rank' => $email_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q !== '' && $job_rank > 0 && $job_title !== '') {
                $key = strtolower($job_title);
                if (!isset($seen_jobs[$key])) {
                    $seen_jobs[$key] = true;
                    $jobs[] = array(
                        'value' => $job_title,
                        'label' => $job_title,
                        'kind' => 'job',
                        'hint' => __('Job', 'modern-job-board'),
                        'rank' => $job_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q === '' && count($names) >= $limit) {
                break;
            }
        }

        $groups = $q === '' ? array($names) : array($names, $emails, $jobs);
        $items = array();
        foreach ($groups as $group) {
            usort($group, static function ($a, $b) use ($q) {
                if ($q === '') {
                    return ((int) $b['posted_ts']) <=> ((int) $a['posted_ts']);
                }
                $cmp = ((int) $b['rank']) <=> ((int) $a['rank']);
                if ($cmp !== 0) {
                    return $cmp;
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
     * @param string $q
     * @return int
     */
    public static function match_rank($haystack, $q)
    {
        if (class_exists('MJB_Admin_Jobs')) {
            return MJB_Admin_Jobs::match_rank($haystack, $q);
        }
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

        return strpos($hay, $q) !== false ? 1 : 0;
    }

    /**
     * @param string $action
     * @param int    $application_id
     * @return true|WP_Error
     */
    public static function run_action($action, $application_id)
    {
        $application_id = (int) $application_id;
        $action = self::sanitize_action($action);
        $post = get_post($application_id);
        if (!$post || $post->post_type !== 'job_application') {
            return new WP_Error('invalid_application', __('Application not found.', 'modern-job-board'));
        }

        if ($action === '') {
            return new WP_Error('unknown_action', __('Unknown action.', 'modern-job-board'));
        }

        if ($action === 'delete') {
            if (!current_user_can('delete_post', $application_id)) {
                return new WP_Error('forbidden', __('You cannot delete this application.', 'modern-job-board'));
            }
            if (function_exists('wp_trash_post')) {
                $trashed = wp_trash_post($application_id);
                if ($trashed) {
                    return true;
                }
            }

            return wp_delete_post($application_id, true) ? true : new WP_Error('delete_failed', __('Could not delete the application.', 'modern-job-board'));
        }

        if (!class_exists('MJB_Application_Status')) {
            return new WP_Error('status_unavailable', __('Application status is unavailable.', 'modern-job-board'));
        }

        if (!MJB_Application_Status::update_status($application_id, $action)) {
            return new WP_Error('status_failed', __('Could not update application status.', 'modern-job-board'));
        }

        return true;
    }

    /**
     * @return void
     */
    public static function ajax_action()
    {
        check_ajax_referer('mjb_admin_tabs', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'modern-job-board')), 403);
        }

        $action = isset($_POST['application_action']) ? self::sanitize_action(wp_unslash($_POST['application_action'])) : '';
        $ids = array();
        if (isset($_POST['application_ids']) && is_array($_POST['application_ids'])) {
            $ids = array_values(array_filter(array_map('absint', wp_unslash($_POST['application_ids']))));
        } elseif (isset($_POST['application_id'])) {
            $ids = array(absint($_POST['application_id']));
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids) || $action === '') {
            wp_send_json_error(array('message' => __('Nothing to do.', 'modern-job-board')), 400);
        }

        $ok = 0;
        $errors = array();
        foreach ($ids as $application_id) {
            $result = self::run_action($action, $application_id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }
            $ok++;
        }

        if ($ok === 0) {
            wp_send_json_error(array(
                'message' => $errors ? $errors[0] : __('Could not update applications.', 'modern-job-board'),
            ), 400);
        }

        wp_send_json_success(array(
            'updated' => $ok,
            'errors' => $errors,
            'message' => self::action_message($action, $ok),
        ));
    }

    /**
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
     * @return void
     */
    public static function handle_export()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }
        check_admin_referer('mjb_export_applications');

        $ids = array();
        if (isset($_REQUEST['application_ids'])) {
            $raw = wp_unslash($_REQUEST['application_ids']);
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
        header('Content-Disposition: attachment; filename=mjb-applications-' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('application_id', 'candidate', 'email', 'job_id', 'job', 'company', 'status', 'has_resume', 'applied'));

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
                $row['candidate'],
                $row['email'],
                $row['job_id'],
                $row['job_title'],
                $row['company'],
                $row['status'],
                !empty($row['has_resume']) ? '1' : '0',
                $row['posted_ymd'],
            ));
        }
        fclose($out);
        exit;
    }

    /**
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
        $job_filter = (int) $state['job'] > 0 ? (string) (int) $state['job'] : '';
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--applications mjb-jobs mjb-applications"
             data-tab-panel="applications"
             data-view="<?php echo esc_attr($state['view']); ?>"
             data-q="<?php echo esc_attr($state['q']); ?>"
             data-job="<?php echo esc_attr($job_filter); ?>"
             data-orderby="<?php echo esc_attr($state['orderby']); ?>"
             data-order="<?php echo esc_attr($state['order']); ?>"
             data-per="<?php echo esc_attr((string) $state['per']); ?>"
             data-page="<?php echo esc_attr((string) $state['page']); ?>"
             data-total="<?php echo esc_attr((string) $data['total']); ?>">

            <div class="mjb-sec mjb-jobs__sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('inbox', 22);
                    esc_html_e('Applications', 'modern-job-board');
                ?></h2>
                <?php if ((int) $counts['new'] > 0) : ?>
                <span class="mjb-hint mjb-hint--warn"><?php echo esc_html(sprintf(
                    _n('%d new application', '%d new applications', (int) $counts['new'], 'modern-job-board'),
                    (int) $counts['new']
                )); ?></span>
                <?php endif; ?>
                <span class="mjb-topbar__spacer"></span>
                <div class="mjb-range" role="group" aria-label="<?php esc_attr_e('Row density', 'modern-job-board'); ?>" id="mjb-applications-density">
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

            <div class="mjb-jobs-views" role="tablist" aria-label="<?php esc_attr_e('Saved views', 'modern-job-board'); ?>">
                <?php
                self::render_view_pill('all', __('All applications', 'modern-job-board'), $counts['all'], $state['view'], false);
                self::render_view_pill('new', __('New', 'modern-job-board'), $counts['new'], $state['view'], $counts['new'] > 0);
                self::render_view_pill('no_resume', __('No resume', 'modern-job-board'), $counts['no_resume'], $state['view'], $counts['no_resume'] > 0);
                $status_views = array('reviewed', 'shortlisted', 'rejected', 'hired');
                $show_status = false;
                foreach ($status_views as $status_view) {
                    if (self::should_show_view_pill($status_view, $counts[$status_view], $state['view'])) {
                        $show_status = true;
                        break;
                    }
                }
                if ($show_status) {
                    echo '<span class="mjb-jobs-views__rule" aria-hidden="true"></span>';
                    self::render_view_pill('reviewed', __('Reviewed', 'modern-job-board'), $counts['reviewed'], $state['view'], false);
                    self::render_view_pill('shortlisted', __('Shortlisted', 'modern-job-board'), $counts['shortlisted'], $state['view'], false);
                    self::render_view_pill('rejected', __('Rejected', 'modern-job-board'), $counts['rejected'], $state['view'], false);
                    self::render_view_pill('hired', __('Hired', 'modern-job-board'), $counts['hired'], $state['view'], false);
                }
                ?>
            </div>

            <div class="mjb-jobs-toolbar">
                <div class="mjb-jobs-search mjb-ac<?php echo $state['q'] !== '' ? ' is-filled' : ''; ?>" id="mjb-applications-search" data-mjb-ac="admin_applications" data-free-text="1">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('search', 16);
                    ?>
                    <input type="text" class="mjb-ac__input" id="mjb-applications-q" value="<?php echo esc_attr($state['q']); ?>" placeholder="<?php esc_attr_e('Search candidate, email or job', 'modern-job-board'); ?>" aria-label="<?php esc_attr_e('Search applications', 'modern-job-board'); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mjb-applications-ac-list" aria-haspopup="listbox">
                    <button class="mjb-jobs-search__clear" type="button" aria-label="<?php esc_attr_e('Clear search', 'modern-job-board'); ?>">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('x', 14);
                        ?>
                    </button>
                    <div id="mjb-applications-ac-list" class="mjb-ac__menu mjb-jobs-ac" data-mjb-ac-menu hidden role="listbox"></div>
                </div>
                <?php self::render_filter_menu('job', __('Job', 'modern-job-board'), $data['filters']['jobs'], $job_filter); ?>
                <span class="mjb-topbar__spacer"></span>
                <span class="mjb-jobs-toolbar__meta" id="mjb-applications-result-meta"><?php echo esc_html(self::result_meta_text($state, $data['total'])); ?></span>
            </div>

            <div class="mjb-panel mjb-jobs-panel" id="mjb-applications-panel" data-density="comfortable">
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
                            __('No application matches “%s”.', 'modern-job-board'),
                            $state['q']
                        )
                        : __('Try a different view, or widen the filters.', 'modern-job-board')
                    ); ?></p>
                    <button class="mjb-btn mjb-btn-outline" type="button" id="mjb-applications-reset"><?php esc_html_e('Clear search and filters', 'modern-job-board'); ?></button>
                </div>
                <?php else : ?>
                <div class="mjb-jobs-table-wrap">
                    <table class="mjb-jobs-table" id="mjb-applications-table">
                        <caption class="mjb-sr-only"><?php echo esc_html(sprintf(
                            /* translators: 1: shown count, 2: total */
                            __('Applications, %1$d of %2$d', 'modern-job-board'),
                            count($rows),
                            $data['total']
                        )); ?></caption>
                        <thead>
                            <tr>
                                <th class="mjb-jobs-col-check" scope="col">
                                    <input type="checkbox" id="mjb-applications-sel-all" aria-label="<?php esc_attr_e('Select all applications on this page', 'modern-job-board'); ?>">
                                </th>
                                <?php self::render_sort_th('title', 'text', 'mjb-jobs-col-job', __('Candidate', 'modern-job-board'), $sort_for('title')); ?>
                                <?php self::render_sort_th('status', 'text', 'mjb-jobs-col-status', __('Status', 'modern-job-board'), $sort_for('status')); ?>
                                <?php self::render_sort_th('job', 'text', 'mjb-jobs-col-for', __('Job', 'modern-job-board'), $sort_for('job')); ?>
                                <?php self::render_sort_th('posted', 'num', 'mjb-jobs-col-date', __('Applied', 'modern-job-board'), $sort_for('posted')); ?>
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
                            __('Showing <b>%1$s–%2$s</b> of <b>%3$s</b> applications', 'modern-job-board'),
                            number_format_i18n($from),
                            number_format_i18n($to),
                            number_format_i18n($data['total'])
                        ),
                        array('b' => array())
                    ); ?></span>
                    <span class="mjb-topbar__spacer"></span>
                    <div class="mjb-jobs-perpage">
                        <span id="mjb-applications-per-label"><?php esc_html_e('Per page', 'modern-job-board'); ?></span>
                        <button class="mjb-jobs-per-btn mjb-js-popup" id="mjb-applications-per-btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
                            /* translators: %d: current per-page size */
                            __('Applications per page, currently %d', 'modern-job-board'),
                            (int) $state['per']
                        )); ?>">
                            <span id="mjb-applications-per-value"><?php echo esc_html((string) $state['per']); ?></span>
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                            echo MJB_Icons::render('chevron-down', 14);
                            ?>
                        </button>
                        <div class="mjb-jobs-menu mjb-jobs-per-menu" id="mjb-applications-per-menu" role="menu" aria-labelledby="mjb-applications-per-label" hidden>
                            <?php foreach (self::per_page_options() as $n) : ?>
                            <button type="button" role="menuitemradio" aria-checked="<?php echo $n === (int) $state['per'] ? 'true' : 'false'; ?>" data-per="<?php echo esc_attr((string) $n); ?>"><?php echo esc_html((string) $n); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php self::render_pager($data['page'], $data['pages']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mjb-jobs-bulkbar" id="mjb-applications-bulkbar" role="region" aria-label="<?php esc_attr_e('Bulk actions', 'modern-job-board'); ?>">
                <span class="mjb-jobs-bulkbar__n" id="mjb-applications-bulk-count"><?php esc_html_e('0 selected', 'modern-job-board'); ?></span>
                <span class="mjb-jobs-bulkbar__rule" aria-hidden="true"></span>
                <button type="button" data-bulk="reviewed"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('circle-check', 15);
                    esc_html_e('Mark reviewed', 'modern-job-board');
                ?></button>
                <button type="button" data-bulk="shortlisted"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('star', 15);
                    esc_html_e('Shortlist', 'modern-job-board');
                ?></button>
                <button type="button" data-bulk="rejected"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('x', 15);
                    esc_html_e('Reject', 'modern-job-board');
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
                <button type="button" class="mjb-jobs-bulkbar__close" id="mjb-applications-bulk-clear" aria-label="<?php esc_attr_e('Clear selection', 'modern-job-board'); ?>">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('x', 16);
                    ?>
                </button>
            </div>
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
                <button type="button" role="menuitemradio" data-value="" aria-checked="<?php echo $current === '' ? 'true' : 'false'; ?>"><?php esc_html_e('All', 'modern-job-board'); ?></button>
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
        $name = (string) $row['candidate'];
        $edit = (string) $row['edit_url'];
        $job_edit = (string) $row['job_edit_url'];
        ?>
        <tr data-status="<?php echo esc_attr($row['status']); ?>"
            data-application-id="<?php echo esc_attr((string) $row['id']); ?>"
            data-job-id="<?php echo esc_attr((string) (int) $row['job_id']); ?>"
            data-resume-url="<?php echo esc_url((string) $row['resume_url']); ?>"
            data-job-url="<?php echo esc_url((string) $row['job_view_url']); ?>">
            <td class="mjb-jobs-col-check">
                <input type="checkbox" aria-label="<?php echo esc_attr(sprintf(
                    /* translators: %s: candidate name */
                    __('Select %s', 'modern-job-board'),
                    $name
                )); ?>">
            </td>
            <td class="mjb-jobs-col-job" data-sort="<?php echo esc_attr(strtolower($name)); ?>">
                <?php if ($edit) : ?>
                <a class="mjb-jobs-title" href="<?php echo esc_url($edit); ?>"><span class="mjb-u"><?php echo esc_html($name); ?></span></a>
                <?php else : ?>
                <span class="mjb-jobs-title"><?php echo esc_html($name); ?></span>
                <?php endif; ?>
                <?php if ($row['email'] !== '') : ?>
                <div class="mjb-jobs-meta"><span class="mjb-jobs-meta__co"><?php echo esc_html($row['email']); ?></span></div>
                <?php endif; ?>
                <div class="mjb-jobs-flags">
                    <?php if (empty($row['has_resume'])) : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php esc_html_e('No resume', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-status" data-sort="<?php echo esc_attr($row['status']); ?>">
                <?php self::render_status_chip($row); ?>
            </td>
            <td class="mjb-jobs-col-for" data-label="<?php esc_attr_e('Job', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr(strtolower((string) $row['job_title'])); ?>">
                <?php if ($row['job_title'] !== '' && $job_edit) : ?>
                <a class="mjb-jobs-title" href="<?php echo esc_url($job_edit); ?>"><span class="mjb-u"><?php echo esc_html($row['job_title']); ?></span></a>
                <?php elseif ($row['job_title'] !== '') : ?>
                <span class="mjb-jobs-title"><?php echo esc_html($row['job_title']); ?></span>
                <?php else : ?>
                <span class="mjb-jobs-title"><?php esc_html_e('Job removed', 'modern-job-board'); ?></span>
                <?php endif; ?>
                <?php if ($row['company'] !== '') : ?>
                <div class="mjb-jobs-meta"><span class="mjb-jobs-meta__co"><?php echo esc_html($row['company']); ?></span></div>
                <?php endif; ?>
            </td>
            <td class="mjb-jobs-col-date" data-label="<?php esc_attr_e('Applied', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['posted_ymd']); ?>">
                <div class="mjb-jobs-date"><?php echo esc_html($row['posted_label']); ?><span><?php echo esc_html($row['posted_rel']); ?></span></div>
            </td>
            <td class="mjb-jobs-col-act">
                <div class="mjb-jobs-actions">
                    <?php self::render_primary_actions($row); ?>
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
        $status = (string) $row['status'];
        $label = class_exists('MJB_Application_Status')
            ? MJB_Application_Status::get_label($status)
            : ucfirst($status);

        if ($status === 'new') {
            echo '<span class="mjb-chip mjb-chip--warn">' . esc_html($label) . '</span>';
            return;
        }
        if ($status === 'reviewed') {
            echo '<span class="mjb-chip mjb-chip--muted">' . esc_html($label) . '</span>';
            return;
        }
        if ($status === 'rejected') {
            echo '<span class="mjb-chip mjb-chip--crit">' . esc_html($label) . '</span>';
            return;
        }
        if ($status === 'hired') {
            echo '<span class="mjb-chip mjb-chip--solid">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render('circle-check', 12);
            echo esc_html($label);
            echo '</span>';
            return;
        }
        echo '<span class="mjb-chip">' . esc_html($label) . '</span>';
    }

    /**
     * @param array<string, mixed> $row
     * @return void
     */
    private static function render_primary_actions($row)
    {
        $name = (string) $row['candidate'];
        $status = (string) $row['status'];

        if ($status === 'new') {
            self::render_act_btn('reviewed', 'circle-check', sprintf(__('Mark %s as reviewed', 'modern-job-board'), $name), __('Mark reviewed', 'modern-job-board'));
        }
        if (!empty($row['resume_url'])) {
            self::render_act_link($row['resume_url'], 'download', sprintf(__('Download resume for %s', 'modern-job-board'), $name), __('Download resume', 'modern-job-board'), true);
        } else {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Open application for %s', 'modern-job-board'), $name), __('Open application', 'modern-job-board'));
        }
        ?>
        <button class="mjb-jobs-act mjb-js-popup" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
            /* translators: %s: candidate name */
            __('More actions for %s', 'modern-job-board'),
            $name
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
     * @return void
     */
    private static function render_act_link($url, $icon, $aria, $tip, $blank = false)
    {
        if ($url === '') {
            return;
        }
        ?>
        <a class="mjb-jobs-act" href="<?php echo esc_url($url); ?>" data-tip="<?php echo esc_attr($tip); ?>" aria-label="<?php echo esc_attr($aria); ?>"<?php
            echo $blank ? ' target="_blank" rel="noopener noreferrer"' : '';
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
        echo '<nav class="mjb-jobs-pager" aria-label="' . esc_attr__('Application pages', 'modern-job-board') . '">';
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
            'post_type' => 'job_application',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));

        $now = (int) current_time('timestamp');
        $rows = array();
        foreach ((array) $ids as $application_id) {
            $application_id = (int) $application_id;
            if (get_post_type($application_id) !== 'job_application') {
                continue;
            }
            $rows[] = self::build_row($application_id, $now);
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
     * @param int $application_id
     * @param int $now
     * @return array<string, mixed>
     */
    public static function build_row($application_id, $now)
    {
        $application_id = (int) $application_id;
        $candidate = (string) get_post_meta($application_id, '_candidate_name', true);
        if ($candidate === '') {
            $candidate = (string) get_the_title($application_id);
        }
        if ($candidate === '') {
            $candidate = __('Unnamed candidate', 'modern-job-board');
        }

        $email = (string) get_post_meta($application_id, '_candidate_email', true);
        $job_id = (int) get_post_meta($application_id, '_job_applied_for', true);
        $job_title = '';
        $company = '';
        $job_edit_url = '';
        $job_view_url = '';
        if ($job_id > 0) {
            $job_title = (string) get_the_title($job_id);
            $company_id = (int) get_post_meta($job_id, '_company_id', true);
            if ($company_id > 0) {
                $company = (string) get_the_title($company_id);
            }
            if ($company === '') {
                $company = (string) get_post_meta($job_id, '_company_name', true);
            }
            $job_edit_url = (string) get_edit_post_link($job_id, 'raw');
            $job_view_url = (string) get_permalink($job_id);
        }

        $resume_id = (int) get_post_meta($application_id, '_candidate_resume_id', true);
        $resume_path = (string) get_post_meta($application_id, '_candidate_resume_path', true);
        $resume_rel = (string) get_post_meta($application_id, '_candidate_resume_relative', true);
        $has_resume = $resume_id > 0 || $resume_path !== '' || $resume_rel !== '';
        $resume_url = '';
        if ($has_resume && class_exists('MJB_Resumes')) {
            $resume_url = (string) MJB_Resumes::get_application_download_url($application_id);
        }

        $status = class_exists('MJB_Application_Status')
            ? MJB_Application_Status::get_status($application_id)
            : 'new';

        $posted_raw = get_the_date('U', $application_id);
        $posted_ts = is_numeric($posted_raw) ? (int) $posted_raw : strtotime((string) $posted_raw);
        if (!$posted_ts) {
            $posted_ts = $now;
        }

        return array(
            'id' => $application_id,
            'candidate' => $candidate,
            'email' => $email,
            'job_id' => $job_id,
            'job_title' => $job_title,
            'company' => $company,
            'status' => $status,
            'has_resume' => $has_resume,
            'resume_url' => $resume_url,
            'posted_ts' => $posted_ts,
            'posted_ymd' => (int) gmdate('Ymd', $posted_ts),
            'posted_label' => self::format_day($posted_ts),
            'posted_rel' => self::relative_time($posted_ts, $now),
            'edit_url' => (string) get_edit_post_link($application_id, 'raw'),
            'job_edit_url' => $job_edit_url,
            'job_view_url' => $job_view_url,
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
            'new' => 0,
            'no_resume' => 0,
            'reviewed' => 0,
            'shortlisted' => 0,
            'rejected' => 0,
            'hired' => 0,
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
        $jobs = array();
        foreach ($catalog as $row) {
            $job_id = (int) $row['job_id'];
            $label = (string) $row['job_title'];
            if ($job_id <= 0 || $label === '') {
                continue;
            }
            if (!isset($jobs[$job_id])) {
                $jobs[$job_id] = array(
                    'value' => (string) $job_id,
                    'label' => $label,
                );
            }
        }
        $jobs = array_values($jobs);
        usort($jobs, static function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return array(
            'jobs' => $jobs,
        );
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
        if (function_exists('human_time_diff')) {
            return sprintf(
                /* translators: %s: relative time */
                __('%s ago', 'modern-job-board'),
                human_time_diff($ts, $now)
            );
        }
        $diff = max(0, (int) $now - (int) $ts);
        $n = max(1, (int) round($diff / DAY_IN_SECONDS));

        return sprintf(_n('%d day ago', '%d days ago', $n, 'modern-job-board'), $n);
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
            $sort = __('sorted by candidate', 'modern-job-board');
        } elseif ($state['orderby'] === 'job') {
            $sort = __('sorted by job', 'modern-job-board');
        } elseif ($state['orderby'] === 'status') {
            $sort = __('sorted by status', 'modern-job-board');
        }

        if ($state['q'] !== '') {
            return sprintf(
                /* translators: %d: match count */
                _n('Search results · %d application', 'Search results · %d applications', $total, 'modern-job-board'),
                $total
            );
        }

        if ($state['view'] !== 'all') {
            return sprintf(
                /* translators: 1: count, 2: sort label */
                _n('%1$d application in view · %2$s', '%1$d applications in view · %2$s', $total, 'modern-job-board'),
                $total,
                $sort
            );
        }

        return sprintf(
            /* translators: 1: count, 2: sort label */
            __('%1$d applications · %2$s', 'modern-job-board'),
            $total,
            $sort
        );
    }

    /**
     * @param string $action
     * @param int    $count
     * @return string
     */
    private static function action_message($action, $count)
    {
        switch ($action) {
            case 'reviewed':
                return sprintf(_n('Marked %d application as reviewed.', 'Marked %d applications as reviewed.', $count, 'modern-job-board'), $count);
            case 'shortlisted':
                return sprintf(_n('Shortlisted %d application.', 'Shortlisted %d applications.', $count, 'modern-job-board'), $count);
            case 'rejected':
                return sprintf(_n('Rejected %d application.', 'Rejected %d applications.', $count, 'modern-job-board'), $count);
            case 'hired':
                return sprintf(_n('Marked %d application as hired.', 'Marked %d applications as hired.', $count, 'modern-job-board'), $count);
            case 'new':
                return sprintf(_n('Reopened %d application.', 'Reopened %d applications.', $count, 'modern-job-board'), $count);
            case 'delete':
                return sprintf(_n('Moved %d application to trash.', 'Moved %d applications to trash.', $count, 'modern-job-board'), $count);
            default:
                return sprintf(_n('Updated %d application.', 'Updated %d applications.', $count, 'modern-job-board'), $count);
        }
    }
}
