<?php
/**
 * WP-admin dashboard tab: overview stats, attention, performance, activity.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Admin_Dashboard
{
    const RANGE_DAYS = 30;
    const FEED_LIMIT = 5;
    const TOP_JOBS_LIMIT = 5;

    /**
     * Counts shown on primary nav tabs.
     *
     * @return array<string, int>
     */
    public static function get_tab_counts()
    {
        return array(
            'jobs' => self::count_posts('job_listing', 'publish'),
            'applications' => self::count_posts('job_application', 'publish'),
            'companies' => self::count_posts('company', 'publish'),
            'resumes' => self::count_posts('mjb_resume', 'publish'),
        );
    }

    /**
     * Structured dashboard data for the selected comparison window.
     *
     * View totals are all-time (view meta is not a time series). Job, company,
     * and application deltas use post dates in the comparison window.
     *
     * @param int $range_days
     * @return array<string, mixed>
     */
    public static function get_snapshot($range_days = self::RANGE_DAYS)
    {
        $range_days = self::sanitize_range_days($range_days);
        $now = (int) current_time('timestamp');
        $window_start = $now - ($range_days * DAY_IN_SECONDS);
        $prev_start = $window_start - ($range_days * DAY_IN_SECONDS);

        $job_count = self::count_posts('job_listing', 'publish');
        $app_count = self::count_posts('job_application', 'publish');
        $company_count = self::count_posts('company', 'publish');
        $pending_companies = self::count_posts('company', 'pending');
        $plan_unlimited = MJB_License::can('unlimited_jobs');
        $plan_limit = MJB_License::get_free_job_limit();
        $over_limit = $plan_unlimited ? 0 : max(0, $job_count - $plan_limit);

        $job_stats = MJB_Analytics::get_admin_job_stats();
        $performance = MJB_Analytics::summarize_job_stats($job_stats);
        $views = isset($performance['views']) ? (int) $performance['views'] : 0;
        $conversion = isset($performance['conversion_rate']) ? (float) $performance['conversion_rate'] : 0.0;

        $expiring = self::collect_expiring_jobs($now, 7);
        $zero_views = self::count_zero_view_jobs($job_stats);
        $external_apply = self::count_external_apply_jobs($job_stats);
        $top_jobs = self::build_top_jobs($job_stats);
        $latest_jobs = self::collect_latest_jobs($job_stats);
        $latest_companies = self::collect_latest_companies();
        $health = self::collect_health_checks();
        $health_pass = 0;
        foreach ($health as $check) {
            if (!empty($check['ok'])) {
                $health_pass++;
            }
        }

        $compare = $range_days > 0;
        if ($compare) {
            $jobs_in_window = self::count_by_date('job_listing', array('publish'), $window_start, $now);
            $jobs_prev = self::count_by_date('job_listing', array('publish'), $prev_start, $window_start);
            $apps_in_window = self::count_by_date('job_application', array('publish', 'pending', 'draft'), $window_start, $now);
            $apps_prev = self::count_by_date('job_application', array('publish', 'pending', 'draft'), $prev_start, $window_start);
            $companies_in_window = self::count_by_date('company', array('publish', 'pending'), $window_start, $now);
            $companies_prev = self::count_by_date('company', array('publish', 'pending'), $prev_start, $window_start);
            $revenue = self::sum_package_revenue($window_start, $now);
            $revenue_prev = self::sum_package_revenue($prev_start, $window_start);
        } else {
            $jobs_in_window = $job_count;
            $jobs_prev = $job_count;
            $apps_in_window = $app_count;
            $apps_prev = $app_count;
            $companies_in_window = $company_count;
            $companies_prev = $company_count;
            $revenue = self::sum_package_revenue(0, $now + 1);
            $revenue_prev = $revenue;
        }

        $has_monetization = self::has_paid_listing_products();
        $pending_webhooks = class_exists('MJB_Webhook_Queue') ? (int) MJB_Webhook_Queue::get_pending_count() : 0;

        $attention = self::build_attention_items(array(
            'over_limit' => $over_limit,
            'job_count' => $job_count,
            'plan_limit' => $plan_limit,
            'app_count' => $app_count,
            'views' => $views,
            'external_apply' => $external_apply,
            'has_monetization' => $has_monetization,
            'expiring' => $expiring,
            'zero_views' => $zero_views,
            'pending_companies' => $pending_companies,
            'pending_webhooks' => $pending_webhooks,
        ));

        return array(
            'range_days' => $range_days,
            'range_compare' => $compare,
            'currency' => self::get_currency_code(),
            'currency_prefix' => self::get_currency_prefix(),
            'revenue' => (int) round($revenue),
            'revenue_delta' => (int) round($revenue - $revenue_prev),
            'orders_url' => admin_url('edit.php?post_type=shop_order'),
            'has_woocommerce' => class_exists('WooCommerce'),
            'job_count' => $job_count,
            'app_count' => $app_count,
            'company_count' => $company_count,
            'pending_companies' => $pending_companies,
            'views' => $views,
            'conversion' => $conversion,
            'plan_unlimited' => $plan_unlimited,
            'plan_limit' => $plan_limit,
            'over_limit' => $over_limit,
            'has_monetization' => $has_monetization,
            'jobs_delta' => $jobs_in_window - $jobs_prev,
            'apps_delta' => $apps_in_window - $apps_prev,
            'companies_delta' => $companies_in_window - $companies_prev,
            'apps_in_window' => $apps_in_window,
            'jobs_in_window' => $jobs_in_window,
            'companies_in_window' => $companies_in_window,
            'external_apply' => $external_apply,
            'zero_views' => $zero_views,
            'expiring' => $expiring,
            'attention' => $attention,
            'health' => $health,
            'health_pass' => $health_pass,
            'health_total' => count($health),
            'top_jobs' => $top_jobs,
            'latest_jobs' => $latest_jobs,
            'latest_companies' => $latest_companies,
            'pending_webhooks' => $pending_webhooks,
            'export_url' => wp_nonce_url(admin_url('admin-post.php?action=mjb_export_analytics'), 'mjb_export_analytics'),
            'urls' => array(
                'jobs' => MJB_Admin_Tabs::get_tab_url('jobs'),
                'jobs_zero_views' => MJB_Admin_Tabs::get_tab_url('jobs', array('mjb_list' => 'zero_views')),
                'jobs_expiring' => MJB_Admin_Tabs::get_tab_url('jobs', array('mjb_list' => 'expiring')),
                'jobs_no_apps' => MJB_Admin_Tabs::get_tab_url('jobs', array('mjb_list' => 'no_apps')),
                'applications' => MJB_Admin_Tabs::get_tab_url('applications'),
                'companies' => MJB_Admin_Tabs::get_tab_url('companies'),
                'companies_pending' => MJB_Admin_Tabs::get_tab_url('companies', array('mjb_list' => 'pending')),
                'license' => MJB_Admin_Tabs::get_tab_url('settings', array('settings_tab' => 'license')),
                'monetization' => MJB_Admin_Tabs::get_tab_url('settings', array('settings_tab' => 'payment')),
                'maps' => MJB_Admin_Tabs::get_tab_url('settings', array('settings_tab' => 'listing')),
                'integrations' => MJB_Admin_Tabs::get_tab_url('settings', array('settings_tab' => 'integrations')),
                'tools' => MJB_Admin_Tabs::get_tab_url('tools'),
                'setup' => MJB_Admin_Tabs::get_tab_url('setup'),
                'reading' => admin_url('options-reading.php'),
            ),
        );
    }

    /**
     * Range from the current request, defaulting to 30 days.
     *
     * @return int
     */
    public static function requested_range_days()
    {
        if (!isset($_REQUEST['range'])) {
            return self::RANGE_DAYS;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view param.
        return self::sanitize_range_days(wp_unslash($_REQUEST['range']));
    }

    /**
     * Allowed Jobs/Companies list filters from dashboard attention links.
     *
     * @param string $filter
     * @param string $post_type
     * @return string
     */
    public static function sanitize_list_filter($filter, $post_type = 'job_listing')
    {
        $filter = sanitize_key((string) $filter);
        $allowed = array(
            'job_listing' => array('zero_views', 'expiring', 'no_apps'),
            'company' => array('pending'),
        );
        $ok = isset($allowed[$post_type]) ? $allowed[$post_type] : array();

        return in_array($filter, $ok, true) ? $filter : '';
    }

    /**
     * Post IDs for an admin list filter. Null means “no filter”.
     *
     * @param string $filter
     * @param string $post_type
     * @return int[]|null
     */
    public static function get_filtered_post_ids($filter, $post_type = 'job_listing')
    {
        $filter = self::sanitize_list_filter($filter, $post_type);
        if ($filter === '') {
            return null;
        }

        if ($post_type === 'company' && $filter === 'pending') {
            return array_map('intval', (array) get_posts(array(
                'post_type' => 'company',
                'post_status' => 'pending',
                'posts_per_page' => -1,
                'fields' => 'ids',
            )));
        }

        if ($post_type !== 'job_listing') {
            return array();
        }

        if ($filter === 'expiring') {
            return self::collect_expiring_jobs((int) current_time('timestamp'), 7)['ids'];
        }

        $job_stats = MJB_Analytics::get_admin_job_stats();
        $ids = array();
        foreach ($job_stats as $row) {
            $job_id = (int) ($row['job_id'] ?? 0);
            if (!$job_id) {
                continue;
            }
            if ($filter === 'zero_views' && (int) ($row['views'] ?? 0) === 0) {
                $ids[] = $job_id;
            }
            if ($filter === 'no_apps' && (int) ($row['applications'] ?? 0) === 0) {
                $ids[] = $job_id;
            }
        }

        return $ids;
    }

    /**
     * Allowed range control values (days). 0 = all time.
     *
     * @return int[]
     */
    public static function get_range_options()
    {
        return array(7, 30, 90, 0);
    }

    /**
     * @param int $range_days
     * @return int
     */
    public static function sanitize_range_days($range_days)
    {
        $range_days = (int) $range_days;
        if (!in_array($range_days, self::get_range_options(), true)) {
            return self::RANGE_DAYS;
        }

        return $range_days;
    }

    /**
     * Plugin wordmark SVG, or a text fallback.
     *
     * @return string
     */
    public static function render_logo_html()
    {
        $path = MJB_PATH . 'assets/images/mjb-logo.svg';
        if (!is_readable($path)) {
            return '<span class="mjb-logo-fallback">' . esc_html__('Modern Job Board', 'modern-job-board') . '</span>';
        }

        $svg = file_get_contents($path);
        if (!is_string($svg) || $svg === '') {
            return '<span class="mjb-logo-fallback">' . esc_html__('Modern Job Board', 'modern-job-board') . '</span>';
        }

        return $svg;
    }

    /**
     * Render the dashboard tab.
     *
     * @return void
     */
    public static function render()
    {
        $data = self::get_snapshot(self::requested_range_days());
        $urls = $data['urls'];
        $range_days = (int) $data['range_days'];
        ?>
        <div class="mjb-tab-panel mjb-tab-panel--dashboard mjb-dash" data-range="<?php echo esc_attr((string) $range_days); ?>">
            <div class="mjb-sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('summary', 20);
                    esc_html_e('Overview', 'modern-job-board');
                ?></h2>
                <span class="mjb-hint" id="mjb-range-hint"><?php echo esc_html(self::range_hint_label($range_days)); ?></span>
                <span class="mjb-sec__spacer"></span>
                <div class="mjb-range" role="group" aria-label="<?php esc_attr_e('Date range', 'modern-job-board'); ?>">
                    <span class="mjb-range__thumb" aria-hidden="true"></span>
                    <?php foreach (self::get_range_options() as $days) :
                        $pressed = $days === $range_days ? 'true' : 'false';
                        ?>
                    <button type="button" data-range="<?php echo esc_attr((string) $days); ?>" aria-pressed="<?php echo esc_attr($pressed); ?>"><?php echo esc_html(self::range_button_label($days)); ?></button>
                    <?php endforeach; ?>
                </div>
                <a class="mjb-btn mjb-btn-outline mjb-btn--icon" href="<?php echo esc_url($data['export_url']); ?>" aria-label="<?php esc_attr_e('Export report (CSV)', 'modern-job-board'); ?>" data-tip="<?php esc_attr_e('Export report (CSV)', 'modern-job-board'); ?>" data-tip-pos="left">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('download', 16);
                    ?>
                </a>
            </div>

            <div class="mjb-overview-stats">
                <?php self::render_stat_revenue($data, $urls); ?>
                <?php self::render_stat_jobs($data); ?>
                <?php self::render_stat_applications($data, $urls); ?>
                <?php self::render_stat_views($data); ?>
                <?php self::render_stat_companies($data); ?>
            </div>

            <div class="mjb-sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('triangle-alert', 20);
                    esc_html_e('Needs Your Attention', 'modern-job-board');
                ?></h2>
                <span class="mjb-hint"><?php echo esc_html(sprintf(
                    _n('%d item', '%d items', count($data['attention']), 'modern-job-board'),
                    count($data['attention'])
                )); ?></span>
            </div>

            <div class="mjb-dash-cols mjb-dash-cols--two-one">
                <?php self::render_attention_panel($data, $urls); ?>
                <?php self::render_health_panel($data, $urls); ?>
            </div>

            <div class="mjb-sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('trending-up', 20);
                    esc_html_e('Performance', 'modern-job-board');
                ?></h2>
                <span class="mjb-hint"><?php esc_html_e('All-time views', 'modern-job-board'); ?></span>
            </div>

            <div class="mjb-dash-cols mjb-dash-cols--half">
                <?php self::render_top_jobs_panel($data, $urls); ?>
                <?php self::render_applications_panel($data, $urls); ?>
            </div>

            <div class="mjb-sec">
                <h2><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('activity', 20);
                    esc_html_e('Latest Activity', 'modern-job-board');
                ?></h2>
                <span class="mjb-hint"><?php esc_html_e('Most recent listings', 'modern-job-board'); ?></span>
            </div>

            <div class="mjb-dash-cols mjb-dash-cols--half">
                <?php self::render_latest_jobs_panel($data, $urls); ?>
                <?php self::render_latest_companies_panel($data, $urls); ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param string $type
     * @param string $status
     * @return int
     */
    private static function count_posts($type, $status)
    {
        $counts = wp_count_posts($type);
        if (!$counts || !isset($counts->{$status})) {
            return 0;
        }

        return (int) $counts->{$status};
    }

    /**
     * Count posts whose stored date falls in [start, end).
     *
     * @param string   $post_type
     * @param string[] $statuses
     * @param int      $start
     * @param int      $end
     * @return int
     */
    private static function count_by_date($post_type, $statuses, $start, $end)
    {
        $ids = get_posts(array(
            'post_type' => $post_type,
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $count = 0;
        foreach ((array) $ids as $post_id) {
            $ts = self::post_timestamp((int) $post_id);
            if ($ts >= $start && $ts < $end) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param int $post_id
     * @return int
     */
    private static function post_timestamp($post_id)
    {
        $raw = get_the_date('Y-m-d H:i:s', $post_id);
        $ts = $raw ? strtotime((string) $raw) : false;

        return $ts ? (int) $ts : 0;
    }

    /**
     * @return string
     */
    private static function get_currency_code()
    {
        $code = strtoupper((string) get_option('mjb_currency', 'USD'));
        return preg_match('/^[A-Z]{3}$/', $code) ? $code : 'USD';
    }

    /**
     * Short prefix for the revenue stat (matches the mock's leading symbol).
     *
     * @return string
     */
    public static function get_currency_prefix()
    {
        $code = self::get_currency_code();
        $map = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'NZD' => 'NZ$',
        );

        return isset($map[$code]) ? $map[$code] : $code;
    }

    /**
     * WooCommerce totals for MJB package / membership line items in [start, end).
     *
     * @param int $start
     * @param int $end
     * @return float
     */
    public static function sum_package_revenue($start, $end)
    {
        if (array_key_exists('mjb_test_package_revenue', $GLOBALS)) {
            return (float) $GLOBALS['mjb_test_package_revenue'];
        }

        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders') || !MJB_License::can('woocommerce')) {
            return 0.0;
        }

        $start = (int) $start;
        $end = (int) $end;
        $args = array(
            'limit' => 200,
            'status' => array('wc-completed', 'wc-processing', 'completed', 'processing'),
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        );
        if ($end > $start && $start > 0) {
            $args['date_created'] = gmdate('Y-m-d H:i:s', $start) . '...' . gmdate('Y-m-d H:i:s', max($start, $end - 1));
        }

        $orders = wc_get_orders($args);
        if (!is_array($orders) && !($orders instanceof Traversable)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_items')) {
                continue;
            }
            $created = 0;
            if (method_exists($order, 'get_date_created') && $order->get_date_created()) {
                $created = (int) $order->get_date_created()->getTimestamp();
            }
            if ($start > 0 && ($created < $start || $created >= $end)) {
                continue;
            }
            foreach ($order->get_items() as $item) {
                if (!self::order_item_is_mjb($item)) {
                    continue;
                }
                $line = 0.0;
                if (method_exists($item, 'get_total')) {
                    $line += (float) $item->get_total();
                }
                if (method_exists($item, 'get_total_tax')) {
                    $line += (float) $item->get_total_tax();
                }
                $total += $line;
            }
        }

        return $total;
    }

    /**
     * @param mixed $item WC order item.
     * @return bool
     */
    private static function order_item_is_mjb($item)
    {
        if (!is_object($item) || !method_exists($item, 'get_product_id')) {
            return false;
        }

        $pid = (int) $item->get_product_id();
        if ($pid && get_post_meta($pid, '_mjb_package_qty', true) !== '') {
            return true;
        }
        if ($pid && get_post_meta($pid, '_mjb_cv_access_duration', true) !== '') {
            return true;
        }
        if ($pid && class_exists('MJB_Memberships')) {
            if (get_post_meta($pid, MJB_Memberships::META_CANDIDATE_PACK, true) === 'yes') {
                return true;
            }
            if (get_post_meta($pid, MJB_Memberships::META_FEATURE_PRODUCT, true) === 'yes') {
                return true;
            }
        }
        if (method_exists($item, 'get_meta') && ($item->get_meta('_mjb_job_id') || $item->get_meta('_mjb_feature_job_id'))) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private static function has_paid_listing_products()
    {
        if (!class_exists('WooCommerce') || !MJB_License::can('woocommerce')) {
            return false;
        }

        $ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'fields' => 'ids',
        ));

        foreach ((array) $ids as $product_id) {
            $qty = get_post_meta((int) $product_id, '_mjb_package_qty', true);
            if ($qty !== '' && $qty !== null && (int) $qty > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $job_stats
     * @return int
     */
    private static function count_zero_view_jobs($job_stats)
    {
        $n = 0;
        foreach ($job_stats as $row) {
            if ((int) ($row['views'] ?? 0) === 0) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param array<int, array<string, mixed>> $job_stats
     * @return int
     */
    private static function count_external_apply_jobs($job_stats)
    {
        $n = 0;
        foreach ($job_stats as $row) {
            $job_id = (int) ($row['job_id'] ?? 0);
            if (!$job_id) {
                continue;
            }
            $method = (string) get_post_meta($job_id, '_application_method', true);
            if (in_array($method, array('external', 'url'), true)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param int $now
     * @param int $within_days
     * @return array{count:int, ids:int[], earliest_title:string, earliest_date:string}
     */
    private static function collect_expiring_jobs($now, $within_days)
    {
        $until = $now + ($within_days * DAY_IN_SECONDS);
        $ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $matched = array();
        $earliest_ts = 0;
        $earliest_title = '';
        $earliest_date = '';

        foreach ((array) $ids as $job_id) {
            $expires = (string) get_post_meta((int) $job_id, '_job_expires', true);
            if ($expires === '') {
                continue;
            }
            $ts = strtotime($expires);
            if (!$ts || $ts < $now || $ts > $until) {
                continue;
            }
            $matched[] = (int) $job_id;
            if ($earliest_ts === 0 || $ts < $earliest_ts) {
                $earliest_ts = $ts;
                $earliest_title = get_the_title((int) $job_id);
                $earliest_date = function_exists('date_i18n')
                    ? date_i18n(get_option('date_format', 'j M'), $ts)
                    : date('j M', $ts);
            }
        }

        return array(
            'count' => count($matched),
            'ids' => $matched,
            'earliest_title' => $earliest_title,
            'earliest_date' => $earliest_date,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $job_stats
     * @return array<int, array<string, mixed>>
     */
    private static function build_top_jobs($job_stats)
    {
        $top = array_slice($job_stats, 0, self::TOP_JOBS_LIMIT);
        $max = 1;
        $shown_views = 0;
        foreach ($top as $row) {
            $v = (int) ($row['views'] ?? 0);
            $shown_views += $v;
            if ($v > $max) {
                $max = $v;
            }
        }

        $out = array();
        foreach ($top as $row) {
            $views = (int) ($row['views'] ?? 0);
            $job_id = (int) ($row['job_id'] ?? 0);
            $out[] = array(
                'job_id' => $job_id,
                'title' => (string) ($row['title'] ?? ''),
                'views' => $views,
                'pct' => (int) round(($views / $max) * 100),
                'edit_url' => $job_id ? get_edit_post_link($job_id, 'raw') : '',
            );
        }

        $total_views = 0;
        foreach ($job_stats as $row) {
            $total_views += (int) ($row['views'] ?? 0);
        }
        $remaining_jobs = max(0, count($job_stats) - count($top));
        $remaining_views = max(0, $total_views - $shown_views);

        return array(
            'rows' => $out,
            'remaining_jobs' => $remaining_jobs,
            'remaining_views' => $remaining_views,
            'remaining_pct' => $max > 0 ? (int) round(($remaining_views / $max) * 100) : 0,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $job_stats
     * @return array<int, array<string, mixed>>
     */
    private static function collect_latest_jobs($job_stats)
    {
        $ids = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => self::FEED_LIMIT,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));

        $by_id = array();
        foreach ($job_stats as $row) {
            $by_id[(int) ($row['job_id'] ?? 0)] = $row;
        }

        $out = array();
        foreach ((array) $ids as $job_id) {
            $job_id = (int) $job_id;
            $stats = isset($by_id[$job_id]) ? $by_id[$job_id] : array();
            $company_id = (int) get_post_meta($job_id, '_company_id', true);
            $company = $company_id ? get_the_title($company_id) : (string) get_post_meta($job_id, '_company_name', true);
            $location = class_exists('MJB_Location') ? MJB_Location::format_job_location($job_id) : '';
            $out[] = array(
                'id' => $job_id,
                'title' => get_the_title($job_id),
                'company' => $company,
                'location' => $location,
                'views' => (int) ($stats['views'] ?? 0),
                'applications' => (int) ($stats['applications'] ?? 0),
                'date' => get_the_date(get_option('date_format', 'j M'), $job_id),
                'edit_url' => get_edit_post_link($job_id, 'raw'),
            );
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collect_latest_companies()
    {
        $posts = get_posts(array(
            'post_type' => 'company',
            'post_status' => array('publish', 'pending'),
            'posts_per_page' => self::FEED_LIMIT,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $out = array();
        foreach ((array) $posts as $post) {
            $id = is_object($post) ? (int) $post->ID : (int) $post;
            $status = get_post_status($id);
            $contact = (string) get_post_meta($id, '_company_contact_name', true);
            $out[] = array(
                'id' => $id,
                'title' => get_the_title($id),
                'contact' => $contact,
                'pending' => $status === 'pending',
                'date' => get_the_date(get_option('date_format', 'j M'), $id),
                'edit_url' => get_edit_post_link($id, 'raw'),
            );
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collect_health_checks()
    {
        $jobs_page = MJB_Page_Resolver::resolve_page_id('mjb_jobs', 'mjb_jobs_page_id');
        $dash_page = MJB_Page_Resolver::resolve_page_id('mjb_dashboard', 'mjb_employer_dashboard_page_id');
        $form_page = MJB_Page_Resolver::resolve_page_id('mjb_job_form', 'mjb_job_form_page_id');
        $maps = trim((string) get_option('mjb_google_maps_api_key', ''));
        $public = (string) get_option('blog_public', '1');

        $checks = array(
            array(
                'ok' => $jobs_page > 0,
                'label' => __('Job search page published', 'modern-job-board'),
                'detail' => '',
                'action_label' => __('Create pages', 'modern-job-board'),
                'tab' => 'setup',
            ),
            array(
                'ok' => $dash_page > 0,
                'label' => __('Employer dashboard published', 'modern-job-board'),
                'detail' => '',
                'action_label' => __('Create pages', 'modern-job-board'),
                'tab' => 'setup',
            ),
            array(
                'ok' => true,
                'label' => __('JobPosting structured data active', 'modern-job-board'),
                'detail' => '',
                'action_label' => '',
                'tab' => '',
            ),
            array(
                'ok' => $form_page > 0,
                'label' => __('Post-a-job page published', 'modern-job-board'),
                'detail' => '',
                'action_label' => __('Create pages', 'modern-job-board'),
                'tab' => 'setup',
            ),
            array(
                'ok' => $maps !== '',
                'label' => __('Google Maps API key missing', 'modern-job-board'),
                'ok_label' => __('Google Maps API key set', 'modern-job-board'),
                'detail' => __('Location search falls back to text matching.', 'modern-job-board'),
                'action_label' => __('Add key', 'modern-job-board'),
                'tab' => 'settings',
                'tab_args' => array('settings_tab' => 'listing'),
            ),
            array(
                'ok' => $public !== '0',
                'label' => __('Search engines are discouraged from indexing this site', 'modern-job-board'),
                'ok_label' => __('Jobs included in sitemap', 'modern-job-board'),
                'detail' => __('WordPress is set to discourage search engines from indexing this site.', 'modern-job-board'),
                'action_label' => __('Reading settings', 'modern-job-board'),
                'url' => admin_url('options-reading.php'),
            ),
        );

        foreach ($checks as $i => $check) {
            if (!empty($check['ok']) && !empty($check['ok_label'])) {
                $checks[$i]['label'] = $check['ok_label'];
            }
        }

        return $checks;
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<int, array<string, mixed>>
     */
    private static function build_attention_items($ctx)
    {
        $items = array();

        if ((int) $ctx['over_limit'] > 0) {
            $items[] = array(
                'tone' => 'crit',
                'badge' => (string) (int) $ctx['over_limit'],
                'title' => __('Jobs above your plan limit', 'modern-job-board'),
                /* translators: 1: published jobs, 2: free plan limit */
                'detail' => sprintf(__('%1$d published jobs on a Free plan limited to %2$d. Extra listings stay pending until you upgrade.', 'modern-job-board'), (int) $ctx['job_count'], (int) $ctx['plan_limit']),
                'action' => __('Upgrade', 'modern-job-board'),
                'tab' => 'settings',
                'tab_args' => array('settings_tab' => 'license'),
            );
        }

        if ((int) $ctx['app_count'] === 0 && (int) $ctx['job_count'] > 0) {
            $detail = __('No applications have been received yet.', 'modern-job-board');
            if ((int) $ctx['external_apply'] > 0) {
                $detail = sprintf(
                    /* translators: %d: jobs using an external apply URL */
                    _n('%d listing sends applicants to an external URL.', '%d listings send applicants to an external URL.', (int) $ctx['external_apply'], 'modern-job-board'),
                    (int) $ctx['external_apply']
                );
            }
            $items[] = array(
                'tone' => 'warn',
                'badge' => '0',
                'title' => __('No applications received on any listing', 'modern-job-board'),
                'detail' => $detail,
                'action' => __('Review jobs', 'modern-job-board'),
                'tab' => 'jobs',
                'tab_args' => array('mjb_list' => 'no_apps'),
            );
        }

        if (empty($ctx['has_monetization'])) {
            $items[] = array(
                'tone' => 'warn',
                'badge' => self::get_currency_prefix() . '0',
                'title' => __('Paid posting is switched off', 'modern-job-board'),
                'detail' => sprintf(
                    /* translators: %d: published job count */
                    _n('Every listing is free, so %d job has earned nothing.', 'Every listing is free, so %d jobs have earned nothing.', (int) $ctx['job_count'], 'modern-job-board'),
                    (int) $ctx['job_count']
                ),
                'action' => __('Enable', 'modern-job-board'),
                'tab' => 'settings',
                'tab_args' => array('settings_tab' => 'payment'),
            );
        }

        $expiring = $ctx['expiring'];
        if (!empty($expiring['count'])) {
            $detail = __('Review listings that will expire soon.', 'modern-job-board');
            if (!empty($expiring['earliest_title'])) {
                $detail = sprintf(
                    /* translators: 1: job title, 2: date */
                    __('Earliest: %1$s, %2$s.', 'modern-job-board'),
                    $expiring['earliest_title'],
                    $expiring['earliest_date']
                );
            }
            $items[] = array(
                'tone' => 'warn',
                'badge' => (string) (int) $expiring['count'],
                'title' => __('Jobs expiring within 7 days', 'modern-job-board'),
                'detail' => $detail,
                'action' => __('Review', 'modern-job-board'),
                'tab' => 'jobs',
                'tab_args' => array('mjb_list' => 'expiring'),
            );
        }

        if ((int) $ctx['zero_views'] > 0) {
            $items[] = array(
                'tone' => 'info',
                'badge' => (string) (int) $ctx['zero_views'],
                'title' => __('Jobs with no views', 'modern-job-board'),
                'detail' => __('Usually a missing category, location, or an unindexed page.', 'modern-job-board'),
                'action' => __('See list', 'modern-job-board'),
                'tab' => 'jobs',
                'tab_args' => array('mjb_list' => 'zero_views'),
            );
        }

        if ((int) $ctx['pending_companies'] > 0) {
            $items[] = array(
                'tone' => 'info',
                'badge' => (string) (int) $ctx['pending_companies'],
                'title' => __('Companies awaiting approval', 'modern-job-board'),
                'detail' => __('Pending company profiles are hidden from the public directory.', 'modern-job-board'),
                'action' => __('Approve', 'modern-job-board'),
                'tab' => 'companies',
                'tab_args' => array('mjb_list' => 'pending'),
            );
        }

        if ((int) $ctx['pending_webhooks'] > 0) {
            $items[] = array(
                'tone' => 'warn',
                'badge' => (string) (int) $ctx['pending_webhooks'],
                'title' => __('Webhook deliveries queued for retry', 'modern-job-board'),
                'detail' => __('Failed deliveries will retry automatically.', 'modern-job-board'),
                'action' => __('Review', 'modern-job-board'),
                'tab' => 'settings',
                'tab_args' => array('settings_tab' => 'integrations'),
            );
        }

        return $items;
    }

    /**
     * @param int $days
     * @return string
     */
    public static function range_button_label($days)
    {
        if ((int) $days === 0) {
            return __('All time', 'modern-job-board');
        }

        return sprintf(
            /* translators: %d: number of days */
            __('%d days', 'modern-job-board'),
            (int) $days
        );
    }

    /**
     * @param int $days
     * @return string
     */
    public static function range_hint_label($days)
    {
        if ((int) $days === 0) {
            return __('All-time totals · Views are counted for the life of each listing', 'modern-job-board');
        }

        return sprintf(
            /* translators: %d: number of days */
            __('New listings compared with the previous %d days · Views are all-time', 'modern-job-board'),
            (int) $days
        );
    }

    /**
     * @param int $days
     * @return string
     */
    public static function range_period_label($days)
    {
        if ((int) $days === 0) {
            return __('All time', 'modern-job-board');
        }

        return sprintf(
            /* translators: %d: number of days */
            __('Last %d days', 'modern-job-board'),
            (int) $days
        );
    }

    /**
     * @param int $delta
     * @return array{class:string,label:string}
     */
    private static function delta_display($delta)
    {
        $delta = (int) $delta;
        if ($delta > 0) {
            return array('class' => 'up', 'label' => '+' . $delta);
        }
        if ($delta < 0) {
            return array('class' => 'down', 'label' => (string) $delta);
        }

        return array('class' => 'flat', 'label' => __('no change', 'modern-job-board'));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_stat_revenue($data, $urls)
    {
        $zero = ((int) $data['revenue'] === 0) ? ' zero' : '';
        $tip = !empty($data['has_woocommerce'])
            ? __('Completed and processing WooCommerce orders for job packages, CV access, and featured listings.', 'modern-job-board')
            : __('Activate WooCommerce and a listing package to record board revenue here.', 'modern-job-board');
        $show_delta = !empty($data['range_compare']);
        $delta = $show_delta ? self::delta_display((int) $data['revenue_delta']) : null;
        ?>
        <div class="mjb-overview-stat">
            <div class="mjb-overview-label"><?php esc_html_e('Revenue', 'modern-job-board'); ?> <?php self::render_info($tip); ?></div>
            <div class="mjb-overview-row">
                <span class="mjb-overview-num<?php echo esc_attr($zero); ?>"><small><?php echo esc_html($data['currency_prefix']); ?></small><?php echo esc_html((string) (int) $data['revenue']); ?></span>
                <?php if ($delta) : ?>
                <span class="mjb-delta <?php echo esc_attr($delta['class']); ?>"><?php echo esc_html($delta['label']); ?></span>
                <?php endif; ?>
            </div>
            <div class="mjb-overview-sub">
                <?php if (empty($data['has_monetization'])) : ?>
                    <?php echo esc_html(sprintf(
                        /* translators: %d: published job count */
                        _n('All %d posts went out free', 'All %d posts went out free', (int) $data['job_count'], 'modern-job-board'),
                        (int) $data['job_count']
                    )); ?>
                    · <?php self::render_tab_link($urls['monetization'], __('Enable paid posting', 'modern-job-board'), 'settings', array('settings_tab' => 'payment')); ?>
                <?php else : ?>
                    <?php esc_html_e('Paid listing products are configured.', 'modern-job-board'); ?>
                    <?php if (!empty($data['has_woocommerce'])) : ?>
                        · <a href="<?php echo esc_url($data['orders_url']); ?>"><span class="mjb-u"><?php esc_html_e('View orders', 'modern-job-board'); ?></span><span class="mjb-arw" aria-hidden="true">→</span></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    private static function render_stat_jobs($data)
    {
        $delta = !empty($data['range_compare']) ? self::delta_display((int) $data['jobs_delta']) : null;
        ?>
        <div class="mjb-overview-stat">
            <div class="mjb-overview-label"><?php esc_html_e('Active jobs', 'modern-job-board'); ?></div>
            <div class="mjb-overview-row">
                <span class="mjb-overview-num"><?php echo esc_html((string) (int) $data['job_count']); ?></span>
                <?php if ($delta) : ?>
                <span class="mjb-delta <?php echo esc_attr($delta['class']); ?>"><?php echo esc_html($delta['label']); ?></span>
                <?php endif; ?>
            </div>
            <div class="mjb-overview-sub">
                <?php if ((int) $data['over_limit'] > 0) : ?>
                    <strong><?php echo esc_html(sprintf(
                        /* translators: 1: jobs over limit, 2: free plan limit */
                        __('%1$d over your Free plan limit of %2$d', 'modern-job-board'),
                        (int) $data['over_limit'],
                        (int) $data['plan_limit']
                    )); ?></strong>
                <?php elseif (empty($data['plan_unlimited'])) : ?>
                    <?php echo esc_html(sprintf(
                        /* translators: 1: published jobs, 2: free plan limit */
                        __('%1$d of %2$d on the Free plan', 'modern-job-board'),
                        (int) $data['job_count'],
                        (int) $data['plan_limit']
                    )); ?>
                <?php else : ?>
                    <?php esc_html_e('Unlimited on your current plan', 'modern-job-board'); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    private static function render_stat_views($data)
    {
        $views = (int) $data['views'];
        $jobs = max(1, (int) $data['job_count']);
        $per = $jobs > 0 ? round($views / max(1, (int) $data['job_count']), 1) : 0;
        $tip = __('Job detail-page views, unique per visitor per hour.', 'modern-job-board');
        ?>
        <div class="mjb-overview-stat">
            <div class="mjb-overview-label"><?php esc_html_e('Job views', 'modern-job-board'); ?> <?php self::render_info($tip); ?></div>
            <div class="mjb-overview-row">
                <span class="mjb-overview-num<?php echo $views === 0 ? ' zero' : ''; ?>"><?php echo esc_html((string) $views); ?></span>
            </div>
            <div class="mjb-overview-sub">
                <?php if ((int) $data['job_count'] > 0) : ?>
                    <strong><?php echo esc_html(sprintf(
                        /* translators: %s: average views per job */
                        __('%s views per job', 'modern-job-board'),
                        (string) $per
                    )); ?></strong>
                    <?php if ($per < 1 && (int) $data['job_count'] >= 10) : ?>
                        — <?php echo esc_html(sprintf(
                            /* translators: %d: published job count */
                            __('low for %d listings', 'modern-job-board'),
                            (int) $data['job_count']
                        )); ?>
                    <?php endif; ?>
                <?php else : ?>
                    <?php esc_html_e('Views are tracked when job pages are visited.', 'modern-job-board'); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_stat_applications($data, $urls)
    {
        $delta = !empty($data['range_compare']) ? self::delta_display((int) $data['apps_delta']) : null;
        $apps = (int) $data['app_count'];
        ?>
        <div class="mjb-overview-stat">
            <div class="mjb-overview-label"><?php esc_html_e('Applications', 'modern-job-board'); ?></div>
            <div class="mjb-overview-row">
                <span class="mjb-overview-num<?php echo $apps === 0 ? ' zero' : ''; ?>"><?php echo esc_html((string) $apps); ?></span>
                <?php if ($delta) : ?>
                <span class="mjb-delta <?php echo esc_attr($delta['class']); ?>"><?php echo esc_html($delta['label']); ?></span>
                <?php endif; ?>
            </div>
            <div class="mjb-overview-sub">
                <?php echo esc_html(sprintf(
                    /* translators: %s: conversion percentage */
                    __('%s of views converted', 'modern-job-board'),
                    (string) $data['conversion'] . '%'
                )); ?>
                <?php if ($apps === 0) : ?>
                    · <?php self::render_tab_link($urls['jobs'], __('Open jobs', 'modern-job-board'), 'jobs'); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    private static function render_stat_companies($data)
    {
        $delta = !empty($data['range_compare']) ? self::delta_display((int) $data['companies_delta']) : null;
        ?>
        <div class="mjb-overview-stat">
            <div class="mjb-overview-label"><?php esc_html_e('Companies', 'modern-job-board'); ?></div>
            <div class="mjb-overview-row">
                <span class="mjb-overview-num"><?php echo esc_html((string) (int) $data['company_count']); ?></span>
                <?php if ($delta) : ?>
                <span class="mjb-delta <?php echo esc_attr($delta['class']); ?>"><?php echo esc_html($delta['label']); ?></span>
                <?php endif; ?>
            </div>
            <div class="mjb-overview-sub">
                <?php if ((int) $data['pending_companies'] > 0) : ?>
                    <strong><?php echo esc_html(sprintf(
                        /* translators: %d: pending company count */
                        _n('%d awaiting your approval', '%d awaiting your approval', (int) $data['pending_companies'], 'modern-job-board'),
                        (int) $data['pending_companies']
                    )); ?></strong>
                <?php else : ?>
                    <?php esc_html_e('No companies waiting for approval', 'modern-job-board'); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_attention_panel($data, $urls)
    {
        unset($urls);
        $items = $data['attention'];
        $n = count($items);
        ?>
        <div class="mjb-panel">
            <div class="mjb-panel__head mjb-panel__head--flush">
                <h3><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('list-checks', 18);
                    esc_html_e('Open Items', 'modern-job-board');
                ?></h3>
                <span class="mjb-sec__spacer"></span>
                <span class="mjb-panel__meta"><?php echo esc_html(sprintf(
                    _n('%d open', '%d open', $n, 'modern-job-board'),
                    $n
                )); ?></span>
            </div>
            <div class="mjb-panel__body">
                <?php if (empty($items)) : ?>
                    <div class="mjb-empty">
                        <div class="mjb-empty__icon"><?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                            echo MJB_Icons::render('circle-check', 20);
                        ?></div>
                        <b><?php esc_html_e('Nothing needs attention', 'modern-job-board'); ?></b>
                        <p><?php esc_html_e('Your board is clear of the usual setup and moderation flags.', 'modern-job-board'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($items as $item) :
                        $tab_args = isset($item['tab_args']) ? $item['tab_args'] : array();
                        $href = MJB_Admin_Tabs::get_tab_url($item['tab'], $tab_args);
                        ?>
                    <div class="mjb-attn">
                        <span class="mjb-attn__badge mjb-attn__badge--<?php echo esc_attr($item['tone']); ?>"><?php echo esc_html($item['badge']); ?></span>
                        <div class="mjb-attn__txt">
                            <b><?php echo esc_html($item['title']); ?></b>
                            <span><?php echo esc_html($item['detail']); ?></span>
                        </div>
                        <?php self::render_tab_link($href, $item['action'], $item['tab'], $tab_args, 'mjb-attn__act'); ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_health_panel($data, $urls)
    {
        $pass = (int) $data['health_pass'];
        $total = max(1, (int) $data['health_total']);
        $pct = (int) round(($pass / $total) * 100);
        ?>
        <div class="mjb-panel">
            <div class="mjb-panel__head mjb-panel__head--flush">
                <h3><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('heart-pulse', 18);
                    esc_html_e('Board Health', 'modern-job-board');
                ?></h3>
                <span class="mjb-sec__spacer"></span>
                <span class="mjb-panel__meta"><?php echo esc_html($pass . ' of ' . (int) $data['health_total']); ?></span>
            </div>
            <div class="mjb-panel__progress">
                <div class="mjb-progress" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $pass); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr((string) (int) $data['health_total']); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: 1: passing checks, 2: total checks */ __('%1$d of %2$d checks passing', 'modern-job-board'), $pass, (int) $data['health_total'])); ?>">
                    <i style="<?php echo esc_attr('--w:' . $pct . '%'); ?>"></i>
                </div>
            </div>
            <div class="mjb-panel__body">
                <?php foreach ($data['health'] as $check) :
                    $ok = !empty($check['ok']);
                    ?>
                <div class="mjb-health">
                    <?php if ($ok) : ?>
                        <span class="mjb-health__tick mjb-health__tick--ok" aria-hidden="true"><?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                            echo MJB_Icons::render('check', 10);
                        ?></span>
                    <?php else : ?>
                        <span class="mjb-health__tick mjb-health__tick--todo" aria-hidden="true">!</span>
                    <?php endif; ?>
                    <div class="mjb-health__txt">
                        <?php echo esc_html($check['label']); ?>
                        <?php if (!$ok && (!empty($check['detail']) || !empty($check['action_label']))) : ?>
                            <span>
                                <?php if (!empty($check['detail'])) : ?>
                                    <?php echo esc_html($check['detail']); ?>
                                <?php endif; ?>
                                <?php
                                if (!empty($check['action_label'])) {
                                    if (!empty($check['url'])) {
                                        echo ' ';
                                        printf(
                                            '<a href="%1$s"><span class="mjb-u">%2$s</span><span class="mjb-arw" aria-hidden="true">→</span></a>',
                                            esc_url($check['url']),
                                            esc_html($check['action_label'])
                                        );
                                    } elseif (!empty($check['tab'])) {
                                        $args = isset($check['tab_args']) ? $check['tab_args'] : array();
                                        $href = MJB_Admin_Tabs::get_tab_url($check['tab'], $args);
                                        echo ' ';
                                        self::render_tab_link($href, $check['action_label'], $check['tab'], $args);
                                    }
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        unset($urls);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_top_jobs_panel($data, $urls)
    {
        $pack = $data['top_jobs'];
        ?>
        <div class="mjb-panel">
            <div class="mjb-panel__head">
                <h3><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('bar-chart-3', 18);
                    esc_html_e('Most Viewed Jobs', 'modern-job-board');
                ?></h3>
                <span class="mjb-sec__spacer"></span>
                <?php self::render_tab_link($urls['jobs'], __('All jobs', 'modern-job-board'), 'jobs', array(), 'mjb-panel__meta'); ?>
            </div>
            <?php if (empty($pack['rows'])) : ?>
                <div class="mjb-empty">
                    <div class="mjb-empty__icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('briefcase', 20);
                    ?></div>
                    <b><?php esc_html_e('No view data yet', 'modern-job-board'); ?></b>
                    <p><?php esc_html_e('Views are recorded when someone opens a job detail page.', 'modern-job-board'); ?></p>
                </div>
            <?php else : ?>
                <div class="mjb-bars">
                    <?php foreach ($pack['rows'] as $row) : ?>
                    <div class="mjb-bar">
                        <?php if (!empty($row['edit_url'])) : ?>
                            <a class="mjb-bar__name" href="<?php echo esc_url($row['edit_url']); ?>"><?php echo esc_html($row['title']); ?></a>
                        <?php else : ?>
                            <span class="mjb-bar__name"><?php echo esc_html($row['title']); ?></span>
                        <?php endif; ?>
                        <span class="mjb-bar__val"><?php echo esc_html((string) (int) $row['views']); ?></span>
                        <span class="mjb-bar__track"><i style="<?php echo esc_attr('--w:' . (int) $row['pct'] . '%'); ?>"></i></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ((int) $pack['remaining_jobs'] > 0) : ?>
                    <div class="mjb-bar mjb-bar--rest">
                        <span class="mjb-bar__name"><?php echo esc_html(sprintf(
                            /* translators: %d: remaining job count */
                            _n('Remaining %d job', 'Remaining %d jobs', (int) $pack['remaining_jobs'], 'modern-job-board'),
                            (int) $pack['remaining_jobs']
                        )); ?></span>
                        <span class="mjb-bar__val"><?php echo esc_html((string) (int) $pack['remaining_views']); ?></span>
                        <span class="mjb-bar__track"><i class="mjb-bar__fill--weak" style="<?php echo esc_attr('--w:' . (int) $pack['remaining_pct'] . '%'); ?>"></i></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_applications_panel($data, $urls)
    {
        $apps = (int) $data['app_count'];
        ?>
        <div class="mjb-panel">
            <div class="mjb-panel__head">
                <h3><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('inbox', 18);
                    esc_html_e('Applications', 'modern-job-board');
                ?></h3>
                <span class="mjb-sec__spacer"></span>
                <span class="mjb-panel__meta" id="mjb-apps-meta"><?php
                    if (!empty($data['range_compare'])) {
                        echo esc_html(sprintf(
                            /* translators: 1: applications in window, 2: number of days */
                            _n('%1$d in last %2$d day', '%1$d in last %2$d days', (int) $data['range_days'], 'modern-job-board'),
                            (int) $data['apps_in_window'],
                            (int) $data['range_days']
                        ));
                    } else {
                        echo esc_html(sprintf(
                            /* translators: %d: application count */
                            _n('%d total', '%d total', $apps, 'modern-job-board'),
                            $apps
                        ));
                    }
                ?></span>
            </div>
            <?php if ($apps > 0) : ?>
                <div class="mjb-empty">
                    <div class="mjb-empty__icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('inbox', 20);
                    ?></div>
                    <b><?php echo esc_html(sprintf(
                        _n('%d application on file', '%d applications on file', $apps, 'modern-job-board'),
                        $apps
                    )); ?></b>
                    <p><?php esc_html_e('Open the Applications tab to review candidates and download resumes.', 'modern-job-board'); ?></p>
                    <a class="mjb-btn mjb-btn-outline mjb-js-tab" href="<?php echo esc_url($urls['applications']); ?>" data-tab="applications"><?php esc_html_e('View applications', 'modern-job-board'); ?></a>
                </div>
            <?php else : ?>
                <div class="mjb-empty">
                    <div class="mjb-empty__icon"><?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                        echo MJB_Icons::render('inbox', 20);
                    ?></div>
                    <b><?php esc_html_e('No Applications Yet', 'modern-job-board'); ?></b>
                    <p><?php echo esc_html(sprintf(
                        /* translators: 1: view count, 2: job count */
                        __('With %1$d views and %2$d live jobs, this usually points at the apply path rather than at demand.', 'modern-job-board'),
                        (int) $data['views'],
                        (int) $data['job_count']
                    )); ?></p>
                    <?php if ((int) $data['external_apply'] > 0 || (int) $data['job_count'] > 0) : ?>
                    <ul>
                        <?php if ((int) $data['external_apply'] > 0) : ?>
                        <li><?php echo esc_html(sprintf(
                            _n('%d job redirects to an external URL', '%d jobs redirect to an external URL', (int) $data['external_apply'], 'modern-job-board'),
                            (int) $data['external_apply']
                        )); ?></li>
                        <?php endif; ?>
                        <li><?php esc_html_e('Confirm the apply form is visible on job detail pages', 'modern-job-board'); ?></li>
                    </ul>
                    <?php endif; ?>
                    <a class="mjb-btn mjb-btn-outline mjb-js-tab" href="<?php echo esc_url($urls['jobs']); ?>" data-tab="jobs"><?php esc_html_e('Review jobs', 'modern-job-board'); ?></a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_latest_jobs_panel($data, $urls)
    {
        ?>
        <div class="mjb-panel">
            <div class="mjb-panel__head">
                <h3><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('briefcase', 18);
                    esc_html_e('Latest Jobs', 'modern-job-board');
                ?></h3>
                <span class="mjb-sec__spacer"></span>
                <?php self::render_tab_link($urls['jobs'], __('All jobs', 'modern-job-board'), 'jobs', array(), 'mjb-panel__meta'); ?>
            </div>
            <div class="mjb-panel__body">
                <?php if (empty($data['latest_jobs'])) : ?>
                    <div class="mjb-empty">
                        <b><?php esc_html_e('No jobs yet', 'modern-job-board'); ?></b>
                        <p><?php esc_html_e('Publish a listing to see it here.', 'modern-job-board'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($data['latest_jobs'] as $job) : ?>
                    <div class="mjb-feed">
                        <div class="mjb-feed__txt">
                            <b><?php
                                if (!empty($job['edit_url'])) {
                                    echo '<a href="' . esc_url($job['edit_url']) . '">' . esc_html($job['title']) . '</a>';
                                } else {
                                    echo esc_html($job['title']);
                                }
                            ?></b>
                            <?php if ($job['company'] !== '') : ?>
                                <span><?php echo esc_html($job['company']); ?></span>
                            <?php endif; ?>
                            <div class="mjb-chips">
                                <?php if ($job['location'] !== '') : ?>
                                <span class="mjb-chip mjb-chip--loc"><?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                    echo MJB_Icons::render('map-pin', 13);
                                    echo esc_html($job['location']);
                                ?></span>
                                <?php endif; ?>
                                <span class="mjb-chip<?php echo ((int) $job['applications'] === 0) ? ' mjb-chip--warn' : ''; ?>"><?php echo esc_html(sprintf(
                                    _n('%d application', '%d applications', (int) $job['applications'], 'modern-job-board'),
                                    (int) $job['applications']
                                )); ?></span>
                                <span class="mjb-chip"><?php echo esc_html(sprintf(
                                    _n('%d view', '%d views', (int) $job['views'], 'modern-job-board'),
                                    (int) $job['views']
                                )); ?></span>
                            </div>
                        </div>
                        <span class="mjb-feed__date"><?php echo esc_html($job['date']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $urls
     * @return void
     */
    private static function render_latest_companies_panel($data, $urls)
    {
        ?>
        <div class="mjb-panel">
            <div class="mjb-panel__head">
                <h3><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                    echo MJB_Icons::render('building', 18);
                    esc_html_e('Newest Companies', 'modern-job-board');
                ?></h3>
                <span class="mjb-sec__spacer"></span>
                <?php self::render_tab_link($urls['companies'], __('All companies', 'modern-job-board'), 'companies', array(), 'mjb-panel__meta'); ?>
            </div>
            <div class="mjb-panel__body">
                <?php if (empty($data['latest_companies'])) : ?>
                    <div class="mjb-empty">
                        <b><?php esc_html_e('No companies yet', 'modern-job-board'); ?></b>
                        <p><?php esc_html_e('New employer profiles will show up here.', 'modern-job-board'); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ($data['latest_companies'] as $company) : ?>
                    <div class="mjb-feed">
                        <div class="mjb-feed__txt">
                            <b><?php
                                if (!empty($company['edit_url'])) {
                                    echo '<a href="' . esc_url($company['edit_url']) . '">' . esc_html($company['title']) . '</a>';
                                } else {
                                    echo esc_html($company['title']);
                                }
                            ?></b>
                            <span><?php echo $company['contact'] !== '' ? esc_html($company['contact']) : esc_html__('No contact person yet', 'modern-job-board'); ?></span>
                            <div class="mjb-chips">
                                <?php if (!empty($company['pending'])) : ?>
                                    <span class="mjb-chip mjb-chip--warn"><?php esc_html_e('Pending approval', 'modern-job-board'); ?></span>
                                <?php else : ?>
                                    <span class="mjb-chip"><?php esc_html_e('Approved', 'modern-job-board'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="mjb-feed__date"><?php echo esc_html($company['date']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param string $tip
     * @return void
     */
    private static function render_info($tip)
    {
        ?>
        <span class="mjb-info" tabindex="0" data-tip="<?php echo esc_attr($tip); ?>" aria-label="<?php echo esc_attr($tip); ?>"><?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo MJB_Icons::render('info', 14);
        ?></span>
        <?php
    }

    /**
     * @param string               $url
     * @param string               $label
     * @param string               $tab
     * @param array<string, string> $tab_args
     * @param string               $class
     * @return void
     */
    private static function render_tab_link($url, $label, $tab, $tab_args = array(), $class = '')
    {
        $class = trim('mjb-js-tab ' . $class);
        $attrs = '';
        if (!empty($tab_args['settings_tab'])) {
            $attrs .= ' data-settings-tab="' . esc_attr($tab_args['settings_tab']) . '"';
        }
        if (!empty($tab_args['tools_tab'])) {
            $attrs .= ' data-tools-tab="' . esc_attr($tab_args['tools_tab']) . '"';
        }
        if (isset($tab_args['mjb_list'])) {
            $attrs .= ' data-list-filter="' . esc_attr((string) $tab_args['mjb_list']) . '"';
        }
        ?>
        <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($url); ?>" data-tab="<?php echo esc_attr($tab); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
            <span class="mjb-u"><?php echo esc_html($label); ?></span><span class="mjb-arw" aria-hidden="true">→</span>
        </a>
        <?php
    }
}
