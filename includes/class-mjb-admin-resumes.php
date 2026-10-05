<?php
/**
 * Admin Resumes tab: saved views, search, filters, row and bulk actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Resumes
{
    const PER_PAGE_DEFAULT = 20;
    const USER_PER_PAGE_META = 'mjb_resumes_per_page';

    /** @var array<int, array<string, mixed>>|null */
    private static $catalog = null;

    /**
     * Hooks.
     *
     * @return void
     */
    public static function init()
    {
        add_action('wp_ajax_mjb_admin_resume_action', array(__CLASS__, 'ajax_action'));
        add_action('wp_ajax_mjb_admin_resumes_suggest', array(__CLASS__, 'ajax_suggest'));
        add_action('admin_post_mjb_export_resumes', array(__CLASS__, 'handle_export'));
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
            'missing_file',
            'pdf',
            'word',
            'published',
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
        $allowed = array('title', 'status', 'file', 'posted');

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
        $allowed = array('publish', 'delete');

        return in_array($action, $allowed, true) ? $action : '';
    }

    /**
     * @param mixed $ext
     * @return string
     */
    public static function sanitize_ext($ext)
    {
        $ext = sanitize_key(strtolower((string) $ext));

        return in_array($ext, array('pdf', 'doc', 'docx'), true) ? $ext : '';
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

        $ext = '';
        if (isset($_REQUEST['mjb_ext'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
            $ext = self::sanitize_ext(wp_unslash($_REQUEST['mjb_ext']));
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
            'ext' => $ext,
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

        if ($state['ext'] !== '' && (string) $row['ext'] !== (string) $state['ext']) {
            return false;
        }

        $q = strtolower(trim((string) $state['q']));
        if ($q === '') {
            return true;
        }

        $hay = strtolower(
            $row['candidate'] . ' ' . $row['email'] . ' ' . $row['filename'] . ' ' . $row['title']
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
            case 'missing_file':
                return empty($row['has_file']);
            case 'pdf':
                return $row['ext'] === 'pdf';
            case 'word':
                return $row['ext'] === 'doc' || $row['ext'] === 'docx';
            case 'published':
                return $row['status'] === 'publish';
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
            'publish' => 0,
            'pending' => 1,
            'draft' => 2,
            'private' => 3,
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
            if ($orderby === 'file') {
                return strcasecmp((string) $a['filename'], (string) $b['filename']) * $dir;
            }

            return (((int) $a['posted_ts']) <=> ((int) $b['posted_ts'])) * $dir;
        });

        return $rows;
    }

    /**
     * Predictive suggestions: candidate, email, filename.
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
        $files = array();
        $seen_names = array();
        $seen_emails = array();
        $seen_files = array();

        foreach (self::catalog() as $row) {
            $name = (string) $row['candidate'];
            $email = (string) $row['email'];
            $filename = (string) $row['filename'];
            $posted = (int) $row['posted_ts'];

            $name_rank = call_user_func($rank, $name, $q);
            $email_rank = call_user_func($rank, $email, $q);
            $file_rank = call_user_func($rank, $filename, $q);
            $row_rank = $q === '' ? $name_rank : max($name_rank, $email_rank, $file_rank);

            if ($row_rank > 0 && $name !== '') {
                $key = strtolower($name);
                if (!isset($seen_names[$key])) {
                    $seen_names[$key] = true;
                    $names[] = array(
                        'value' => $name,
                        'label' => $name,
                        'kind' => 'candidate',
                        'hint' => $filename,
                        'rank' => $row_rank,
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

            if ($q !== '' && $file_rank > 0 && $filename !== '') {
                $key = strtolower($filename);
                if (!isset($seen_files[$key])) {
                    $seen_files[$key] = true;
                    $files[] = array(
                        'value' => $filename,
                        'label' => $filename,
                        'kind' => 'file',
                        'hint' => __('File', 'modern-job-board'),
                        'rank' => $file_rank,
                        'posted_ts' => $posted,
                    );
                }
            }

            if ($q === '' && count($names) >= $limit) {
                break;
            }
        }

        $groups = $q === '' ? array($names) : array($names, $emails, $files);
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
     * @param int    $resume_id
     * @return true|WP_Error
     */
    public static function run_action($action, $resume_id)
    {
        $resume_id = (int) $resume_id;
        $action = self::sanitize_action($action);
        $post = get_post($resume_id);
        if (!$post || $post->post_type !== 'mjb_resume') {
            return new WP_Error('invalid_resume', __('Resume not found.', 'modern-job-board'));
        }

        if ($action === '') {
            return new WP_Error('unknown_action', __('Unknown action.', 'modern-job-board'));
        }

        if ($action === 'delete') {
            if (!current_user_can('delete_post', $resume_id)) {
                return new WP_Error('forbidden', __('You cannot delete this resume.', 'modern-job-board'));
            }
            if (function_exists('wp_trash_post')) {
                $trashed = wp_trash_post($resume_id);
                if ($trashed) {
                    return true;
                }
            }

            return wp_delete_post($resume_id, true) ? true : new WP_Error('delete_failed', __('Could not delete the resume.', 'modern-job-board'));
        }

        if ($action === 'publish') {
            if (!current_user_can('edit_post', $resume_id)) {
                return new WP_Error('forbidden', __('You cannot publish this resume.', 'modern-job-board'));
            }
            $updated = wp_update_post(array(
                'ID' => $resume_id,
                'post_status' => 'publish',
            ), true);
            if (is_wp_error($updated)) {
                return $updated;
            }

            return $updated ? true : new WP_Error('publish_failed', __('Could not publish the resume.', 'modern-job-board'));
        }

        return new WP_Error('unknown_action', __('Unknown action.', 'modern-job-board'));
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

        $action = isset($_POST['resume_action']) ? self::sanitize_action(wp_unslash($_POST['resume_action'])) : '';
        $ids = array();
        if (isset($_POST['resume_ids']) && is_array($_POST['resume_ids'])) {
            $ids = array_values(array_filter(array_map('absint', wp_unslash($_POST['resume_ids']))));
        } elseif (isset($_POST['resume_id'])) {
            $ids = array(absint($_POST['resume_id']));
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids) || $action === '') {
            wp_send_json_error(array('message' => __('Nothing to do.', 'modern-job-board')), 400);
        }

        $ok = 0;
        $errors = array();
        foreach ($ids as $resume_id) {
            $result = self::run_action($action, $resume_id);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }
            $ok++;
        }

        if ($ok === 0) {
            wp_send_json_error(array(
                'message' => $errors ? $errors[0] : __('Could not update resumes.', 'modern-job-board'),
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
        check_admin_referer('mjb_export_resumes');

        $ids = array();
        if (isset($_REQUEST['resume_ids'])) {
            $raw = wp_unslash($_REQUEST['resume_ids']);
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
        header('Content-Disposition: attachment; filename=mjb-resumes-' . gmdate('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, array('resume_id', 'candidate', 'email', 'filename', 'ext', 'status', 'has_file', 'uploaded'));

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
                $row['filename'],
                $row['ext'],
                $row['status'],
                !empty($row['has_file']) ? '1' : '0',
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
        $ext_filter = $state['ext'] !== '' ? $state['ext'] : '';
        $heading = class_exists('MJB_Document_Label')
            ? MJB_Document_Label::plural()
            : __('Resumes', 'modern-job-board');
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--resumes mjb-jobs mjb-resumes"
             data-tab-panel="resumes"
             data-view="<?php echo esc_attr($state['view']); ?>"
             data-q="<?php echo esc_attr($state['q']); ?>"
             data-ext="<?php echo esc_attr($ext_filter); ?>"
             data-orderby="<?php echo esc_attr($state['orderby']); ?>"
             data-order="<?php echo esc_attr($state['order']); ?>"
             data-per="<?php echo esc_attr((string) $state['per']); ?>"
             data-page="<?php echo esc_attr((string) $state['page']); ?>"
             data-total="<?php echo esc_attr((string) $data['total']); ?>">

            <div class="mjb-sec mjb-jobs__sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('file-text', 22);
                    echo esc_html($heading);
                ?></h2>
                <?php if ((int) $counts['missing_file'] > 0) : ?>
                <span class="mjb-hint mjb-hint--warn"><?php echo esc_html(sprintf(
                    /* translators: %d: count */
                    _n('%d missing file', '%d missing files', (int) $counts['missing_file'], 'modern-job-board'),
                    (int) $counts['missing_file']
                )); ?></span>
                <?php endif; ?>
                <span class="mjb-topbar__spacer"></span>
                <div class="mjb-range" role="group" aria-label="<?php esc_attr_e('Row density', 'modern-job-board'); ?>" id="mjb-resumes-density">
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
                self::render_view_pill('all', __('All resumes', 'modern-job-board'), $counts['all'], $state['view'], false);
                self::render_view_pill('missing_file', __('Missing file', 'modern-job-board'), $counts['missing_file'], $state['view'], $counts['missing_file'] > 0);
                $type_views = array('pdf', 'word', 'published', 'draft');
                $show_types = false;
                foreach ($type_views as $type_view) {
                    if (self::should_show_view_pill($type_view, $counts[$type_view], $state['view'])) {
                        $show_types = true;
                        break;
                    }
                }
                if ($show_types) {
                    echo '<span class="mjb-jobs-views__rule" aria-hidden="true"></span>';
                    self::render_view_pill('pdf', __('PDF', 'modern-job-board'), $counts['pdf'], $state['view'], false);
                    self::render_view_pill('word', __('Word', 'modern-job-board'), $counts['word'], $state['view'], false);
                    self::render_view_pill('published', __('Published', 'modern-job-board'), $counts['published'], $state['view'], false);
                    self::render_view_pill('draft', __('Drafts', 'modern-job-board'), $counts['draft'], $state['view'], false);
                }
                ?>
            </div>

            <div class="mjb-jobs-toolbar">
                <div class="mjb-jobs-search mjb-ac<?php echo $state['q'] !== '' ? ' is-filled' : ''; ?>" id="mjb-resumes-search" data-mjb-ac="admin_resumes" data-free-text="1">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('search', 16);
                    ?>
                    <input type="text" class="mjb-ac__input" id="mjb-resumes-q" value="<?php echo esc_attr($state['q']); ?>" placeholder="<?php esc_attr_e('Search candidate, email or file', 'modern-job-board'); ?>" aria-label="<?php esc_attr_e('Search resumes', 'modern-job-board'); ?>" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="mjb-resumes-ac-list" aria-haspopup="listbox">
                    <button class="mjb-jobs-search__clear" type="button" aria-label="<?php esc_attr_e('Clear search', 'modern-job-board'); ?>">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('x', 14);
                        ?>
                    </button>
                    <div id="mjb-resumes-ac-list" class="mjb-ac__menu mjb-jobs-ac" data-mjb-ac-menu hidden role="listbox"></div>
                </div>
                <?php self::render_filter_menu('ext', __('Type', 'modern-job-board'), $data['filters']['exts'], $ext_filter); ?>
                <span class="mjb-topbar__spacer"></span>
                <span class="mjb-jobs-toolbar__meta" id="mjb-resumes-result-meta"><?php echo esc_html(self::result_meta_text($state, $data['total'])); ?></span>
            </div>

            <div class="mjb-panel mjb-jobs-panel" id="mjb-resumes-panel" data-density="comfortable">
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
                            __('No resume matches “%s”.', 'modern-job-board'),
                            $state['q']
                        )
                        : __('Try a different view, or widen the filters.', 'modern-job-board')
                    ); ?></p>
                    <button class="mjb-btn mjb-btn-outline" type="button" id="mjb-resumes-reset"><?php esc_html_e('Clear search and filters', 'modern-job-board'); ?></button>
                </div>
                <?php else : ?>
                <div class="mjb-jobs-table-wrap">
                    <table class="mjb-jobs-table" id="mjb-resumes-table">
                        <caption class="mjb-sr-only"><?php echo esc_html(sprintf(
                            /* translators: 1: document label plural, 2: shown count, 3: total */
                            __('%1$s, %2$d of %3$d', 'modern-job-board'),
                            $heading,
                            count($rows),
                            $data['total']
                        )); ?></caption>
                        <thead>
                            <tr>
                                <th class="mjb-jobs-col-check" scope="col">
                                    <input type="checkbox" id="mjb-resumes-sel-all" aria-label="<?php esc_attr_e('Select all resumes on this page', 'modern-job-board'); ?>">
                                </th>
                                <?php self::render_sort_th('title', 'text', 'mjb-jobs-col-job', __('Candidate', 'modern-job-board'), $sort_for('title')); ?>
                                <?php self::render_sort_th('status', 'text', 'mjb-jobs-col-status', __('Status', 'modern-job-board'), $sort_for('status')); ?>
                                <?php self::render_sort_th('file', 'text', 'mjb-jobs-col-for', __('File', 'modern-job-board'), $sort_for('file')); ?>
                                <?php self::render_sort_th('posted', 'num', 'mjb-jobs-col-date', __('Uploaded', 'modern-job-board'), $sort_for('posted')); ?>
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
                            __('Showing <b>%1$s–%2$s</b> of <b>%3$s</b> resumes', 'modern-job-board'),
                            number_format_i18n($from),
                            number_format_i18n($to),
                            number_format_i18n($data['total'])
                        ),
                        array('b' => array())
                    ); ?></span>
                    <span class="mjb-topbar__spacer"></span>
                    <div class="mjb-jobs-perpage">
                        <span id="mjb-resumes-per-label"><?php esc_html_e('Per page', 'modern-job-board'); ?></span>
                        <button class="mjb-jobs-per-btn mjb-js-popup" id="mjb-resumes-per-btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="<?php echo esc_attr(sprintf(
                            /* translators: %d: current per-page size */
                            __('Resumes per page, currently %d', 'modern-job-board'),
                            (int) $state['per']
                        )); ?>">
                            <span id="mjb-resumes-per-value"><?php echo esc_html((string) $state['per']); ?></span>
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                            echo MJB_Icons::render('chevron-down', 14);
                            ?>
                        </button>
                        <div class="mjb-jobs-menu mjb-jobs-per-menu" id="mjb-resumes-per-menu" role="menu" aria-labelledby="mjb-resumes-per-label" hidden>
                            <?php foreach (self::per_page_options() as $n) : ?>
                            <button type="button" role="menuitemradio" aria-checked="<?php echo $n === (int) $state['per'] ? 'true' : 'false'; ?>" data-per="<?php echo esc_attr((string) $n); ?>"><?php echo esc_html((string) $n); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php self::render_pager($data['page'], $data['pages']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mjb-jobs-bulkbar" id="mjb-resumes-bulkbar" role="region" aria-label="<?php esc_attr_e('Bulk actions', 'modern-job-board'); ?>">
                <span class="mjb-jobs-bulkbar__n" id="mjb-resumes-bulk-count"><?php esc_html_e('0 selected', 'modern-job-board'); ?></span>
                <span class="mjb-jobs-bulkbar__rule" aria-hidden="true"></span>
                <button type="button" data-bulk="publish"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('circle-check', 15);
                    esc_html_e('Publish', 'modern-job-board');
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
                <button type="button" class="mjb-jobs-bulkbar__close" id="mjb-resumes-bulk-clear" aria-label="<?php esc_attr_e('Clear selection', 'modern-job-board'); ?>">
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
        $ext_label = $row['ext'] !== '' ? strtoupper((string) $row['ext']) : '';
        ?>
        <tr data-status="<?php echo esc_attr($row['status']); ?>"
            data-resume-id="<?php echo esc_attr((string) $row['id']); ?>"
            data-ext="<?php echo esc_attr((string) $row['ext']); ?>"
            data-resume-url="<?php echo esc_url((string) $row['download_url']); ?>"
            data-edit-url="<?php echo esc_url($edit); ?>">
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
                    <?php if (empty($row['has_file'])) : ?>
                    <span class="mjb-chip mjb-chip--warn mjb-chip--flag"><?php esc_html_e('Missing file', 'modern-job-board'); ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="mjb-jobs-col-status" data-sort="<?php echo esc_attr($row['status']); ?>">
                <?php self::render_status_chip($row); ?>
            </td>
            <td class="mjb-jobs-col-for" data-label="<?php esc_attr_e('File', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr(strtolower((string) $row['filename'])); ?>">
                <?php
                $file_label = $row['filename'] !== '' ? (string) $row['filename'] : __('Untitled', 'modern-job-board');
                ?>
                <span class="mjb-jobs-title" title="<?php echo esc_attr($file_label); ?>"><?php echo esc_html($file_label); ?></span>
                <?php if ($ext_label !== '') : ?>
                <div class="mjb-jobs-flags">
                    <span class="mjb-chip"><?php echo esc_html($ext_label); ?></span>
                </div>
                <?php endif; ?>
            </td>
            <td class="mjb-jobs-col-date" data-label="<?php esc_attr_e('Uploaded', 'modern-job-board'); ?>" data-sort="<?php echo esc_attr((string) (int) $row['posted_ymd']); ?>">
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
        if ($status === 'draft') {
            echo '<span class="mjb-chip mjb-chip--muted">' . esc_html__('Draft', 'modern-job-board') . '</span>';
            return;
        }
        if ($status === 'pending') {
            echo '<span class="mjb-chip mjb-chip--warn">' . esc_html__('Pending', 'modern-job-board') . '</span>';
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
        $name = (string) $row['candidate'];

        if (!empty($row['download_url'])) {
            self::render_act_link($row['download_url'], 'download', sprintf(__('Download resume for %s', 'modern-job-board'), $name), __('Download resume', 'modern-job-board'), true);
        }
        if ($row['status'] === 'draft') {
            self::render_act_btn('publish', 'circle-check', sprintf(__('Publish resume for %s', 'modern-job-board'), $name), __('Publish', 'modern-job-board'));
        } else {
            self::render_act_link($row['edit_url'], 'pencil', sprintf(__('Edit resume for %s', 'modern-job-board'), $name), __('Edit resume', 'modern-job-board'));
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
        echo '<nav class="mjb-jobs-pager" aria-label="' . esc_attr__('Resume pages', 'modern-job-board') . '">';
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
            'post_type' => 'mjb_resume',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));

        $now = (int) current_time('timestamp');
        $rows = array();
        foreach ((array) $ids as $resume_id) {
            $resume_id = (int) $resume_id;
            if (get_post_type($resume_id) !== 'mjb_resume') {
                continue;
            }
            $rows[] = self::build_row($resume_id, $now);
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
     * @param int $resume_id
     * @param int $now
     * @return array<string, mixed>
     */
    public static function build_row($resume_id, $now)
    {
        $resume_id = (int) $resume_id;
        $title = (string) get_the_title($resume_id);
        $user_id = (int) get_post_meta($resume_id, '_candidate_user_id', true);
        $candidate = '';
        $email = '';
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            if ($user) {
                $candidate = (string) $user->display_name;
                $email = isset($user->user_email) ? (string) $user->user_email : '';
            }
        }
        if ($candidate === '' && $title !== '') {
            if (preg_match('/\s+-\s+(.+)$/', $title, $m)) {
                $candidate = $m[1];
            } else {
                $candidate = $title;
            }
        }
        if ($candidate === '') {
            $candidate = __('Unnamed candidate', 'modern-job-board');
        }

        $path = (string) get_post_meta($resume_id, '_resume_file_path', true);
        $relative = (string) get_post_meta($resume_id, '_resume_file_relative', true);
        $has_file = $path !== '' || $relative !== '';
        $filename = self::file_basename($path, $relative, $title);
        $ext = self::file_ext($filename);

        $download_url = '';
        if ($has_file && class_exists('MJB_Resumes')) {
            $download_url = (string) MJB_Resumes::get_resume_post_download_url($resume_id);
        }

        $status = (string) get_post_status($resume_id);
        if ($status === '') {
            $status = 'publish';
        }

        $posted_raw = get_the_date('U', $resume_id);
        $posted_ts = is_numeric($posted_raw) ? (int) $posted_raw : strtotime((string) $posted_raw);
        if (!$posted_ts) {
            $posted_ts = $now;
        }

        return array(
            'id' => $resume_id,
            'title' => $title,
            'candidate' => $candidate,
            'email' => $email,
            'user_id' => $user_id,
            'filename' => $filename,
            'ext' => $ext,
            'status' => $status,
            'has_file' => $has_file,
            'download_url' => $download_url,
            'posted_ts' => $posted_ts,
            'posted_ymd' => (int) gmdate('Ymd', $posted_ts),
            'posted_label' => self::format_day($posted_ts),
            'posted_rel' => self::relative_time($posted_ts, $now),
            'edit_url' => (string) get_edit_post_link($resume_id, 'raw'),
        );
    }

    /**
     * @param string $path
     * @param string $relative
     * @param string $title
     * @return string
     */
    public static function file_basename($path, $relative, $title)
    {
        $from = $relative !== '' ? $relative : $path;
        if ($from !== '') {
            return basename(str_replace('\\', '/', $from));
        }
        $title = (string) $title;
        if (preg_match('/^(.+?)\s+-\s+.+$/', $title, $m)) {
            return $m[1];
        }

        return $title;
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function file_ext($filename)
    {
        $ext = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));

        return in_array($ext, array('pdf', 'doc', 'docx'), true) ? $ext : '';
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, int>
     */
    private static function view_counts($catalog)
    {
        $counts = array(
            'all' => 0,
            'missing_file' => 0,
            'pdf' => 0,
            'word' => 0,
            'published' => 0,
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
        $found = array();
        foreach ($catalog as $row) {
            $ext = (string) $row['ext'];
            if ($ext === '') {
                continue;
            }
            $found[$ext] = true;
        }
        $labels = array(
            'pdf' => 'PDF',
            'doc' => 'DOC',
            'docx' => 'DOCX',
        );
        $exts = array();
        foreach ($labels as $value => $label) {
            if (isset($found[$value])) {
                $exts[] = array(
                    'value' => $value,
                    'label' => $label,
                );
            }
        }

        return array(
            'exts' => $exts,
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
        } elseif ($state['orderby'] === 'file') {
            $sort = __('sorted by file', 'modern-job-board');
        } elseif ($state['orderby'] === 'status') {
            $sort = __('sorted by status', 'modern-job-board');
        }

        if ($state['q'] !== '') {
            return sprintf(
                /* translators: %d: match count */
                _n('Search results · %d resume', 'Search results · %d resumes', $total, 'modern-job-board'),
                $total
            );
        }

        if ($state['view'] !== 'all') {
            return sprintf(
                /* translators: 1: count, 2: sort label */
                _n('%1$d resume in view · %2$s', '%1$d resumes in view · %2$s', $total, 'modern-job-board'),
                $total,
                $sort
            );
        }

        return sprintf(
            /* translators: 1: count, 2: sort label */
            __('%1$d resumes · %2$s', 'modern-job-board'),
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
                return sprintf(_n('Published %d resume.', 'Published %d resumes.', $count, 'modern-job-board'), $count);
            case 'delete':
                return sprintf(_n('Moved %d resume to trash.', 'Moved %d resumes to trash.', $count, 'modern-job-board'), $count);
            default:
                return sprintf(_n('Updated %d resume.', 'Updated %d resumes.', $count, 'modern-job-board'), $count);
        }
    }
}
