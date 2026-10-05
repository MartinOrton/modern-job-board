<?php
/**
 * Admin Companies tab: saved views, search, filters, row and bulk actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Companies
{
    const PER_PAGE_DEFAULT = 20;
    const USER_PER_PAGE_META = 'mjb_companies_per_page';

    /** @var array<int, array<string, mixed>>|null */
    private static $catalog = null;

    /**
     * Hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_ajax_mjb_admin_company_action', array(__CLASS__, 'ajax_action'));
        add_action('wp_ajax_mjb_admin_companies_suggest', array(__CLASS__, 'ajax_suggest'));
        add_action('admin_post_mjb_export_companies', array(__CLASS__, 'handle_export'));
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
            'attention',
            'no_jobs',
            'has_jobs',
            'published',
            'pending',
            'draft',
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
        $allowed = array('title', 'status', 'jobs', 'posted');

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

        $location = '';
        if (isset($_REQUEST['mjb_loc'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $location = sanitize_text_field(wp_unslash($_REQUEST['mjb_loc']));
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
            'location' => $location,
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

        if ($state['location'] !== '' && (string) $row['location'] !== (string) $state['location']) {
            return false;
        }

        $q = strtolower(trim((string) $state['q']));
        if ($q === '') {
            return true;
        }

        $hay = strtolower(
            $row['title'] . ' ' . $row['tagline'] . ' ' . $row['email'] . ' ' . $row['website'] . ' ' . $row['contact'] . ' ' . $row['location']
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
            case 'attention':
                return !empty($row['needs_attention']);
            case 'no_jobs':
                return (int) $row['jobs_published'] === 0;
            case 'has_jobs':
                return (int) $row['jobs_published'] > 0;
            case 'published':
                return $row['status'] === 'publish';
            case 'pending':
                return $row['status'] === 'pending';
            case 'draft':
                return $row['status'] === 'draft';
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
            'private' => 3,
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
            if ($orderby === 'jobs') {
                return (((int) $a['jobs_published']) <=> ((int) $b['jobs_published'])) * $dir;
            }

            return (((int) $a['posted_ts']) <=> ((int) $b['posted_ts'])) * $dir;
        });

        return $rows;
    }

    /**
     * Predictive suggestions: company name, contact, location.
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
        $contacts = array();
        $locations = array();
        $seen_names = array();
        $seen_contacts = array();
        $seen_locations = array();

        foreach (self::catalog() as $row) {
            $title = (string) $row['title'];
            $contact = (string) $row['contact'];
            $location = (string) $row['location'];
            $posted = (int) $row['posted_ts'];

            $title_rank = call_user_func($rank, $title, $q);
            $contact_rank = call_user_func($rank, $contact, $q);
            $location_rank = call_user_func($rank, $location, $q);
            $company_rank = $q === '' ? $title_rank : max($title_rank, $contact_rank, $location_rank);

            if ($company_rank > 0 && $title !== '') {
                $key = strtolower($title);
                if (!isset($seen_names[$key])) {
                    $seen_names[$key] = true;
                    $hint_parts = array();
                    if ($location !== '') {
                        $hint_parts[] = $location;
                    }
                    if ((int) $row['jobs_published'] > 0) {
                        $hint_parts[] = sprintf(
                            _n('%d live job', '%d live jobs', (int) $row['jobs_published'], 'modern-job-board'),
                            (int) $row['jobs_published']
                        );
                    }
                    $names[] = array(
                        'value' => $title,
                        'label' => $title,
                        'kind' => 'company',
                        'hint' => implode(' · ', $hint_parts),
                        'rank' => $company_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q !== '' && $contact_rank > 0 && $contact !== '') {
                $key = strtolower($contact);
                if (!isset($seen_contacts[$key])) {
                    $seen_contacts[$key] = true;
                    $contacts[] = array(
                        'value' => $contact,
                        'label' => $contact,
                        'kind' => 'contact',
                        'hint' => __('Contact', 'modern-job-board'),
                        'rank' => $contact_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q !== '' && $location_rank > 0 && $location !== '') {
                $key = strtolower($location);
                if (!isset($seen_locations[$key])) {
                    $seen_locations[$key] = true;
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

            if ($q === '' && count($names) >= $limit) {
                break;
            }
        }

        $groups = $q === '' ? array($names) : array($names, $contacts, $locations);
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
     * @return true|WP_Error
     */
    public static function run_action($action, $company_id)
    {
        $company_id = (int) $company_id;
        $post = get_post($company_id);
        if (!$post || $post->post_type !== 'company') {
            return new WP_Error('invalid_company', __('Company not found.', 'modern-job-board'));
        }

        switch ($action) {
            case 'approve':
            case 'publish':
                $updated = wp_update_post(array(
                    'ID' => $company_id,
                    'post_status' => 'publish',
                ), true);
                if (is_wp_error($updated)) {
                    return $updated;
                }

                return $updated ? true : new WP_Error('publish_failed', __('Could not publish this company.', 'modern-job-board'));
            case 'delete':
                if (!current_user_can('delete_post', $company_id)) {
                    return new WP_Error('forbidden', __('You cannot delete this company.', 'modern-job-board'));
                }
                if (function_exists('wp_trash_post')) {
                    $trashed = wp_trash_post($company_id);
                    if ($trashed) {
                        return true;
                    }
                }

                return wp_delete_post($company_id, true) ? true : new WP_Error('delete_failed', __('Could not delete the company.', 'modern-job-board'));
            default:
                return new WP_Error('unknown_action', __('Unknown action.', 'modern-job-board'));
        }
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

        $action = isset($_POST['company_action']) ? sanitize_key(wp_unslash($_POST['company_action'])) : '';
        $ids = array();
        if (isset($_POST['company_ids']) && is_array($_POST['company_ids'])) {
            $ids = array_values(array_filter(array_map('absint', wp_unslash($_POST['company_ids']))));
        } elseif (isset($_POST['company_id'])) {
            $ids = array(absint($_POST['company_id']));
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids) || $action === '') {
            wp_send_json_error(array('message' => __('Nothing to do.', 'modern-job-board')), 400);
        }

        $ok = 0;
        $errors = array();
        foreach ($ids as $company_id) {
            $result = self::run_action($action, $company_id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }
            $ok++;
        }

        if ($ok === 0) {
            wp_send_json_error(array(
                'message' => $errors ? $errors[0] : __('Could not update companies.', 'modern-job-board'),
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
        check_admin_referer('mjb_export_companies');

        $ids = array();
        if (isset($_REQUEST['company_ids'])) {
            $raw = wp_unslash($_REQUEST['company_ids']);
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
        header('Content-Disposition: attachment; filename=mjb-companies-' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('company_id', 'name', 'status', 'tagline', 'website', 'email', 'contact', 'location', 'jobs_published', 'jobs_total', 'created'));

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
                $row['tagline'],
                $row['website'],
                $row['email'],
                $row['contact'],
                $row['location'],
                $row['jobs_published'],
                $row['jobs_total'],
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
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--companies mjb-jobs mjb-companies"
             data-tab-panel="companies"
             data-view="<?php echo esc_attr($state['view']); ?>"
             data-q="<?php echo esc_attr($state['q']); ?>"
             data-loc="<?php echo esc_attr($state['location']); ?>"
             data-orderby="<?php echo esc_attr($state['orderby']); ?>"
             data-order="<?php echo esc_attr($state['order']); ?>"
             data-per="<?php echo esc_attr((string) $state['per']); ?>"
             data-page="<?php echo esc_attr((string) $state['page']); ?>"
             data-total="<?php echo esc_attr((string) $data['total']); ?>">

            <div class="mjb-sec mjb-jobs__sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('building', 22);
                    esc_html_e('Companies', 'modern-job-board');
                ?></h2>
                <?php if ((int) $counts['pending'] > 0) : ?>
                <span class="mjb-hint mjb-hint--warn"><?php echo esc_html(sprintf(
                    _n('%d awaiting approval', '%d awaiting approval', (int) $counts['pending'], 'modern-job-board'),
                    (int) $counts['pending']
                )); ?></span>
                <?php endif; ?>
                <span class="mjb-topbar__spacer"></span>
                <div class="mjb-range" role="group" aria-label="<?php esc_attr_e('Row density', 'modern-job-board'); ?>" id="mjb-companies-density">
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
                self::render_view_pill('all', __('All companies', 'modern-job-board'), $counts['all'], $state['view'], false);
                self::render_view_pill('attention', __('Needs attention', 'modern-job-board'), $counts['attention'], $state['view'], $counts['attention'] > 0);
                self::render_view_pill('no_jobs', __('No jobs', 'modern-job-board'), $counts['no_jobs'], $state['view'], false);
                self::render_view_pill('has_jobs', __('Has jobs', 'modern-job-board'), $counts['has_jobs'], $state['view'], false);
                $status_views = array('published', 'pending', 'draft');
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
                    self::render_view_pill('pending', __('Pending review', 'modern-job-board'), $counts['pending'], $state['view'], $counts['pending'] > 0);
                    self::render_view_pill('draft', __('Drafts', 'modern-job-board'), $counts['draft'], $state['view'], false);
                }
                ?>
            </div>

            <div class="mjb-jobs-toolbar">
                <div class="mjb-jobs-search mjb-ac<?php echo $state['q'] !== '' ? ' is-filled' : ''; ?>" id="mjb-companies-search" data-mjb-ac="admin_companies" data-free-text="1">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('search', 16);
                    ?>
                    <input type="text" class="mjb-ac__input" id="mjb-companies-q" value="<?php echo esc_attr($state['q']); ?>" placeholder="<?php esc_attr_e('Search name, contact or location', 'modern-job-board'); ?>" aria-label="<?php esc_attr_e('Search companies', 'modern-job-board'); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mjb-companies-ac-list" aria-haspopup="listbox">
                    <button class="mjb-jobs-search__clear" type="button" aria-label="<?php esc_attr_e('Clear search', 'modern-job-board'); ?>">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('x', 14);
                        ?>
                    </button>
                    <div id="mjb-companies-ac-list" class="mjb-ac__menu mjb-jobs-ac" data-mjb-ac-menu hidden role="listbox"></div>
                </div>
                <?php self::render_filter_menu('loc', __('Location', 'modern-job-board'), $data['filters']['locations'], $state['location']); ?>
                <span class="mjb-topbar__spacer"></span>
                <span class="mjb-jobs-toolbar__meta" id="mjb-companies-result-meta"><?php echo esc_html(self::result_meta_text($state, $data['total'])); ?></span>
            </div>

            <div class="mjb-panel mjb-jobs-panel" id="mjb-companies-panel" data-density="comfortable">
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
                            __('No company matches “%s”.', 'modern-job-board'),
                            $state['q']
                        )
                        : __('Try a different view, or widen the filters.', 'modern-job-board')
                    ); ?></p>
                    <button class="mjb-btn mjb-btn-outline" type="button" id="mjb-companies-reset"><?php esc_html_e('Clear search and filters', 'modern-job-board'); ?></button>
                </div>
                <?php else : ?>
                <div class="mjb-jobs-table-wrap">
                    <table class="mjb-jobs-table" id="mjb-companies-table">
                        <caption class="mjb-sr-only"><?php echo esc_html(sprintf(
                            /* translators: 1: shown count, 2: total */
                            __('Companies, %1$d of %2$d', 'modern-job-board'),
                            count($rows),
                            $data['total']
                        )); ?></caption>
                        <thead>
                            <tr>
                                <th class="mjb-jobs-col-check" scope="col">
                                    <input type="checkbox" id="mjb-companies-sel-all" aria-label="<?php esc_attr_e('Select all companies on this page', 'modern-job-board'); ?>">
                                </th>
                                <?php self::render_sort_th('title', 'text', 'mjb-jobs-col-job', __('Company', 'modern-job-board'), $sort_for('title')); ?>
                                <?php self::render_sort_th('status', 'text', 'mjb-jobs-col-status', __('Status', 'modern-job-board'), $sort_for('status')); ?>
                                <?php self::render_sort_th('jobs', 'num', 'mjb-jobs-col-num', __('Jobs', 'modern-job-board'), $sort_for('jobs')); ?>
                                <?php self::render_sort_th('posted', 'num', 'mjb-jobs-col-date', __('Added', 'modern-job-board'), $sort_for('posted')); ?>
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
                            __('Showing <b>%1$s–%2$s</b> of <b>%3$s</b> companies', 'modern-job-board'),
                            number_format_i18n($from),
                            number_format_i18n($to),
                            number_format_i18n($data['total'])
                        ),
                        array('b' => array())
                    ); ?></span>
                    <span class="mjb-topbar__spacer"></span>
                    <div class="mjb-jobs-perpage">
                        <span id="mjb-companies-per-label"><?php esc_html_e('Per page', 'modern-job-board'); ?></span>
                        <button class="mjb-jobs-per-btn mjb-js-popup" id="mjb-companies-per-btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
                            /* translators: %d: current per-page size */
                            __('Companies per page, currently %d', 'modern-job-board'),
                            (int) $state['per']
                        )); ?>">
                            <span id="mjb-companies-per-value"><?php echo esc_html((string) $state['per']); ?></span>
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                            echo MJB_Icons::render('chevron-down', 14);
                            ?>
                        </button>
                        <div class="mjb-jobs-menu mjb-jobs-per-menu" id="mjb-companies-per-menu" role="menu" aria-labelledby="mjb-companies-per-label" hidden>
                            <?php foreach (self::per_page_options() as $n) : ?>
                            <button type="button" role="menuitemradio" aria-checked="<?php echo $n === (int) $state['per'] ? 'true' : 'false'; ?>" data-per="<?php echo esc_attr((string) $n); ?>"><?php echo esc_html((string) $n); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php self::render_pager($data['page'], $data['pages']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mjb-jobs-bulkbar" id="mjb-companies-bulkbar" role="region" aria-label="<?php esc_attr_e('Bulk actions', 'modern-job-board'); ?>">
                <span class="mjb-jobs-bulkbar__n" id="mjb-companies-bulk-count"><?php esc_html_e('0 selected', 'modern-job-board'); ?></span>
                <span class="mjb-jobs-bulkbar__rule" aria-hidden="true"></span>
                <button type="button" data-bulk="approve"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('circle-check', 15);
                    esc_html_e('Approve', 'modern-job-board');
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
                <button type="button" class="mjb-jobs-bulkbar__close" id="mjb-companies-bulk-clear" aria-label="<?php esc_attr_e('Clear selection', 'modern-job-board'); ?>">
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
        $title = (string) $row['title'];
        $edit = (string) $row['edit_url'];
        $jobs_url = class_exists('MJB_Admin_Tabs')
            ? MJB_Admin_Tabs::get_tab_url('jobs', array('mjb_company' => (int) $row['id']))
            : admin_url('admin.php?page=modern-job-board&tab=jobs&mjb_company=' . (int) $row['id']);
        ?>
        <tr data-status="<?php echo esc_attr($row['list_status']); ?>"
            data-company-id="<?php echo esc_attr((string) $row['id']); ?>"
            data-view-url="<?php echo esc_url($row['view_url']); ?>">
            <td class="mjb-jobs-col-check">
                <input type="checkbox" aria-label="<?php echo esc_attr(sprintf(
                    /* translators: %s: company name */
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
                <?php if ($row['tagline'] !== '' || $row['contact'] !== '') : ?>
                <div class="mjb-jobs-meta"><span class="mjb-jobs-meta__co"><?php echo esc_html($row['tagline'] !== '' ? $row['tagline'] : $row['contact']); ?></span></div>
                <?php endif; ?>
                <div class="mjb-jobs-flags">
                    <?php if ($row['location'] !== '') : ?>
                    <span class="mjb-chip mjb-chip--loc"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('map-pin', 13);
                        echo esc_html($row['location']);
                    ?></span>
                    <?php endif; ?>
                    <?php if ($row['website_host'] !== '') : ?>
                    <span class="mjb-chip"><?php echo esc_html($row['website_host']); ?></span>
                    <?php endif; ?>
                    <?php if ($row['status'] === 'pending') : ?>
                    <span class="mjb-chip mjb-chip--line mjb-chip--flag"><?php esc_html_e('Submitted by employer', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                    <?php if ((int) $row['jobs_published'] === 0 && $row['status'] === 'publish') : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php esc_html_e('No live jobs', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-status" data-sort="<?php echo esc_attr($row['list_status']); ?>">
                <?php self::render_status_chip($row); ?>
                <?php if ($row['status_sub'] !== '') : ?>
                <span class="mjb-jobs-expiry<?php echo !empty($row['expiry_class']) ? ' ' . esc_attr($row['expiry_class']) : ''; ?>"><?php echo esc_html($row['status_sub']); ?></span>
                <?php endif; ?>
            </td>
            <td class="mjb-jobs-col-num" data-label="<?php esc_attr_e('Jobs', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['jobs_published']); ?>">
                <div class="mjb-jobs-metric">
                    <span class="mjb-jobs-metric__val<?php echo (int) $row['jobs_published'] === 0 ? ' is-zero' : ''; ?>"><?php echo esc_html(number_format_i18n((int) $row['jobs_published'])); ?></span>
                    <?php if ($row['jobs_sub'] !== '') : ?>
                    <span class="mjb-jobs-metric__sub"><?php echo esc_html($row['jobs_sub']); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-date" data-label="<?php esc_attr_e('Added', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['posted_ymd']); ?>">
                <div class="mjb-jobs-date"><?php echo esc_html($row['posted_label']); ?><span><?php echo esc_html($row['posted_rel']); ?></span></div>
            </td>
            <td class="mjb-jobs-col-act">
                <div class="mjb-jobs-actions">
                    <?php self::render_primary_actions($row, $jobs_url); ?>
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
        echo '<span class="mjb-chip">' . esc_html__('Published', 'modern-job-board') . '</span>';
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $jobs_url
     * @return void
     */
    private static function render_primary_actions($row, $jobs_url)
    {
        $title = (string) $row['title'];
        $status = $row['list_status'];

        if ($status === 'pending') {
            self::render_act_btn('approve', 'circle-check', sprintf(__('Approve and publish %s', 'modern-job-board'), $title), __('Approve and publish', 'modern-job-board'));
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit company', 'modern-job-board'));
        } elseif ((int) $row['jobs_published'] > 0) {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit company', 'modern-job-board'));
            self::render_act_link($jobs_url, 'briefcase', sprintf(__('View jobs for %s', 'modern-job-board'), $title), __('View jobs', 'modern-job-board'), false, 'jobs', (int) $row['id']);
        } else {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit %s', 'modern-job-board'), $title), __('Edit company', 'modern-job-board'));
            self::render_act_link($row['view_url'], 'eye', sprintf(__('View %s', 'modern-job-board'), $title), __('View on site', 'modern-job-board'), true);
        }
        ?>
        <button class="mjb-jobs-act mjb-js-popup" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
            /* translators: %s: company name */
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
     * @param int    $company_id
     * @return void
     */
    private static function render_act_link($url, $icon, $aria, $tip, $blank = false, $tab = '', $company_id = 0)
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
        if ((int) $company_id > 0) {
            $data .= ' data-company="' . esc_attr((string) (int) $company_id) . '"';
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
        echo '<nav class="mjb-jobs-pager" aria-label="' . esc_attr__('Company pages', 'modern-job-board') . '">';
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
            'post_type' => 'company',
            'post_status' => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));

        $ids = array_map('intval', (array) $ids);
        $stats = self::job_stats();
        $now = (int) current_time('timestamp');
        $rows = array();

        foreach ($ids as $company_id) {
            $rows[] = self::build_row($company_id, $stats, $now);
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
     * @param int                            $company_id
     * @param array<int, array<string,mixed>> $stats
     * @param int                            $now
     * @return array<string, mixed>
     */
    public static function build_row($company_id, $stats, $now)
    {
        $company_id = (int) $company_id;
        $status = (string) get_post_status($company_id);
        $tagline = (string) get_post_meta($company_id, '_company_tagline', true);
        if ($tagline === '') {
            $tagline = (string) get_post_meta($company_id, '_company_motto', true);
        }
        $website = (string) get_post_meta($company_id, '_company_website', true);
        $email = (string) get_post_meta($company_id, '_company_email', true);
        $contact = (string) get_post_meta($company_id, '_company_contact_name', true);

        $stat = isset($stats[$company_id]) ? $stats[$company_id] : array(
            'total' => 0,
            'published' => 0,
            'location' => '',
        );
        $jobs_total = (int) $stat['total'];
        $jobs_published = (int) $stat['published'];
        $location = (string) $stat['location'];

        $posted_raw = get_the_date('U', $company_id);
        $posted_ts = is_numeric($posted_raw) ? (int) $posted_raw : strtotime((string) $posted_raw);
        if (!$posted_ts) {
            $posted_ts = $now;
        }

        $needs_attention = $status === 'pending' || ($status === 'publish' && $jobs_published === 0);

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
        }

        $jobs_sub = '';
        if ($jobs_published === 0) {
            $jobs_sub = __('no live jobs', 'modern-job-board');
        } elseif ($jobs_total > $jobs_published) {
            $jobs_sub = sprintf(
                /* translators: %d: total listings including unpublished */
                __('%d total listings', 'modern-job-board'),
                $jobs_total
            );
        } else {
            $jobs_sub = _n('live listing', 'live listings', $jobs_published, 'modern-job-board');
        }

        return array(
            'id' => $company_id,
            'title' => (string) get_the_title($company_id),
            'status' => $status,
            'list_status' => $status,
            'tagline' => $tagline,
            'website' => $website,
            'website_host' => self::website_host($website),
            'email' => $email,
            'contact' => $contact,
            'location' => $location,
            'jobs_total' => $jobs_total,
            'jobs_published' => $jobs_published,
            'jobs_sub' => $jobs_sub,
            'posted_ts' => $posted_ts,
            'posted_ymd' => (int) gmdate('Ymd', $posted_ts),
            'posted_label' => self::format_day($posted_ts),
            'posted_rel' => self::relative_time($posted_ts, $now),
            'status_sub' => $status_sub,
            'expiry_class' => $expiry_class,
            'needs_attention' => $needs_attention,
            'edit_url' => (string) get_edit_post_link($company_id, 'raw'),
            'view_url' => (string) get_permalink($company_id),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function job_stats()
    {
        $ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ));

        $out = array();
        foreach ((array) $ids as $job_id) {
            $job_id = (int) $job_id;
            $cid = (int) get_post_meta($job_id, '_company_id', true);
            if ($cid <= 0) {
                continue;
            }
            if (!isset($out[$cid])) {
                $out[$cid] = array(
                    'total' => 0,
                    'published' => 0,
                    'locations' => array(),
                );
            }
            $out[$cid]['total']++;
            $status = (string) get_post_status($job_id);
            if ($status === 'publish') {
                $out[$cid]['published']++;
            }
            $location = class_exists('MJB_Location') ? (string) MJB_Location::format_job_location($job_id) : '';
            if ($location !== '') {
                if (!isset($out[$cid]['locations'][$location])) {
                    $out[$cid]['locations'][$location] = 0;
                }
                $out[$cid]['locations'][$location]++;
            }
        }

        foreach ($out as $cid => $stat) {
            $location = '';
            if (!empty($stat['locations'])) {
                arsort($stat['locations']);
                $keys = array_keys($stat['locations']);
                $location = (string) $keys[0];
            }
            $out[$cid]['location'] = $location;
            unset($out[$cid]['locations']);
        }

        return $out;
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
            'no_jobs' => 0,
            'has_jobs' => 0,
            'published' => 0,
            'pending' => 0,
            'draft' => 0,
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
        $locations = array();
        foreach ($catalog as $row) {
            if ($row['location'] !== '') {
                $locations[$row['location']] = array(
                    'value' => $row['location'],
                    'label' => $row['location'],
                );
            }
        }
        $locations = array_values($locations);
        usort($locations, static function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return array(
            'locations' => $locations,
        );
    }

    /**
     * @param string $website
     * @return string
     */
    private static function website_host($website)
    {
        $website = trim((string) $website);
        if ($website === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }
        $parts = function_exists('wp_parse_url') ? wp_parse_url($website) : parse_url($website);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        return preg_replace('/^www\./i', '', (string) $parts['host']);
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
            $sort = __('sorted by name', 'modern-job-board');
        } elseif ($state['orderby'] === 'jobs') {
            $sort = __('sorted by jobs', 'modern-job-board');
        } elseif ($state['orderby'] === 'status') {
            $sort = __('sorted by status', 'modern-job-board');
        }

        if ($state['q'] !== '') {
            return sprintf(
                /* translators: %d: match count */
                _n('Search results · %d company', 'Search results · %d companies', $total, 'modern-job-board'),
                $total
            );
        }

        if ($state['view'] !== 'all') {
            return sprintf(
                /* translators: 1: count, 2: sort label */
                _n('%1$d company in view · %2$s', '%1$d companies in view · %2$s', $total, 'modern-job-board'),
                $total,
                $sort
            );
        }

        return sprintf(
            /* translators: 1: count, 2: sort label */
            __('%1$d companies · %2$s', 'modern-job-board'),
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
            case 'publish':
            case 'approve':
                return sprintf(_n('Published %d company.', 'Published %d companies.', $count, 'modern-job-board'), $count);
            case 'delete':
                return sprintf(_n('Moved %d company to trash.', 'Moved %d companies to trash.', $count, 'modern-job-board'), $count);
            default:
                return sprintf(_n('Updated %d company.', 'Updated %d companies.', $count, 'modern-job-board'), $count);
        }
    }
}
