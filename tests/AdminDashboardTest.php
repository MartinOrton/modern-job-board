<?php

use PHPUnit\Framework\TestCase;

class AdminDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_post_counts'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_dates'] = array();
        $GLOBALS['mjb_test_terms'] = array();
        $GLOBALS['mjb_test_db_results'] = array();
        $GLOBALS['mjb_test_timestamp'] = 1700000000;
        unset($GLOBALS['mjb_test_package_revenue']);
        $_REQUEST = array();
    }

    public function test_sanitize_range_days_defaults_to_thirty()
    {
        $this->assertSame(30, MJB_Admin_Dashboard::sanitize_range_days(15));
        $this->assertSame(7, MJB_Admin_Dashboard::sanitize_range_days(7));
        $this->assertSame(0, MJB_Admin_Dashboard::sanitize_range_days(0));
    }

    public function test_currency_prefix_maps_common_codes()
    {
        $GLOBALS['mjb_test_options']['mjb_currency'] = 'ZAR';
        $this->assertSame('R', MJB_Admin_Dashboard::get_currency_prefix());

        $GLOBALS['mjb_test_options']['mjb_currency'] = 'USD';
        $this->assertSame('$', MJB_Admin_Dashboard::get_currency_prefix());
    }

    public function test_tab_counts_use_published_totals()
    {
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 12, 'pending' => 2);
        $GLOBALS['mjb_test_post_counts']['job_application'] = (object) array('publish' => 3);
        $GLOBALS['mjb_test_post_counts']['company'] = (object) array('publish' => 5, 'pending' => 1);
        $GLOBALS['mjb_test_post_counts']['mjb_resume'] = (object) array('publish' => 0);

        $counts = MJB_Admin_Dashboard::get_tab_counts();

        $this->assertSame(12, $counts['jobs']);
        $this->assertSame(3, $counts['applications']);
        $this->assertSame(5, $counts['companies']);
        $this->assertSame(0, $counts['resumes']);
    }

    public function test_snapshot_flags_jobs_over_free_plan_limit()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => MJB_License::FREE_ACTIVE_JOB_LIMIT + 5);
        $GLOBALS['mjb_test_post_counts']['job_application'] = (object) array('publish' => 0);
        $GLOBALS['mjb_test_post_counts']['company'] = (object) array('publish' => 1, 'pending' => 1);
        $GLOBALS['mjb_test_post_counts']['mjb_resume'] = (object) array('publish' => 0);

        $snapshot = MJB_Admin_Dashboard::get_snapshot(30);

        $this->assertSame(MJB_License::FREE_ACTIVE_JOB_LIMIT + 5, $snapshot['job_count']);
        $this->assertSame(5, $snapshot['over_limit']);
        $this->assertFalse($snapshot['plan_unlimited']);
        $this->assertNotEmpty($snapshot['attention']);

        $titles = array_map(static function ($item) {
            return $item['title'];
        }, $snapshot['attention']);
        $this->assertContains('Jobs above your plan limit', $titles);
        $this->assertContains('Companies awaiting approval', $titles);
    }

    public function test_snapshot_health_includes_six_checks()
    {
        $snapshot = MJB_Admin_Dashboard::get_snapshot();

        $this->assertCount(6, $snapshot['health']);
        $this->assertSame(6, $snapshot['health_total']);
        $this->assertLessThanOrEqual(6, $snapshot['health_pass']);
    }

    public function test_logo_html_reads_bundled_svg()
    {
        $html = MJB_Admin_Dashboard::render_logo_html();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('mjb-logo', $html);
        $this->assertStringContainsString('Modern Job Board', $html);
    }

    public function test_dashboard_render_outputs_upgraded_sections()
    {
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 2);
        $GLOBALS['mjb_test_post_counts']['job_application'] = (object) array('publish' => 0);
        $GLOBALS['mjb_test_post_counts']['company'] = (object) array('publish' => 0, 'pending' => 0);
        $GLOBALS['mjb_test_posts'] = array(11, 12);
        $GLOBALS['mjb_test_post_types'][11] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][12] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][11] = 'publish';
        $GLOBALS['mjb_test_post_status'][12] = 'publish';
        $GLOBALS['mjb_test_titles'][11] = 'Lead Engineer';
        $GLOBALS['mjb_test_titles'][12] = 'Office Manager';
        $GLOBALS['mjb_test_post_meta'][11][MJB_Analytics::VIEW_COUNT_META] = 8;
        $GLOBALS['mjb_test_post_meta'][12][MJB_Analytics::VIEW_COUNT_META] = 0;
        $GLOBALS['mjb_test_terms'][11]['job_location'] = array('London');

        ob_start();
        MJB_Admin_Dashboard::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-tab-panel--dashboard', $html);
        $this->assertStringContainsString('Overview', $html);
        $this->assertStringContainsString('mjb-icon--summary', $html);
        $this->assertStringContainsString('Needs Your Attention', $html);
        $this->assertStringContainsString('Board Health', $html);
        $this->assertStringContainsString('Most Viewed Jobs', $html);
        $this->assertStringContainsString('Latest Jobs', $html);
        $this->assertStringContainsString('Lead Engineer', $html);
        $this->assertStringContainsString('mjb-range', $html);
        $this->assertStringContainsString('data-range="30"', $html);
        $this->assertStringContainsString('All-time views', $html);
        $this->assertStringContainsString('post.php?post=11', $html);
        $this->assertStringContainsString('data-list-filter="zero_views"', $html);
        $this->assertStringContainsString('data-list-filter="no_apps"', $html);
        $this->assertStringContainsString('mjb-chip--loc', $html);
        $this->assertStringContainsString('London', $html);
    }

    public function test_requested_range_days_reads_request()
    {
        $this->assertSame(30, MJB_Admin_Dashboard::requested_range_days());

        $_REQUEST['range'] = '7';
        $this->assertSame(7, MJB_Admin_Dashboard::requested_range_days());

        $_REQUEST['range'] = '0';
        $this->assertSame(0, MJB_Admin_Dashboard::requested_range_days());

        $_REQUEST['range'] = '15';
        $this->assertSame(30, MJB_Admin_Dashboard::requested_range_days());
    }

    public function test_all_time_snapshot_skips_period_comparison()
    {
        $snapshot = MJB_Admin_Dashboard::get_snapshot(0);

        $this->assertSame(0, $snapshot['range_days']);
        $this->assertFalse($snapshot['range_compare']);
        $this->assertSame(0, $snapshot['jobs_delta']);
        $this->assertSame(0, $snapshot['revenue']);
    }

    public function test_snapshot_uses_package_revenue_when_provided()
    {
        $GLOBALS['mjb_test_package_revenue'] = 42.4;
        $snapshot = MJB_Admin_Dashboard::get_snapshot(30);

        $this->assertSame(42, $snapshot['revenue']);
        $this->assertArrayHasKey('orders_url', $snapshot);
    }

    public function test_list_filters_and_attention_targets()
    {
        $this->assertSame('zero_views', MJB_Admin_Dashboard::sanitize_list_filter('zero_views', 'job_listing'));
        $this->assertSame('', MJB_Admin_Dashboard::sanitize_list_filter('zero_views', 'company'));
        $this->assertSame('pending', MJB_Admin_Dashboard::sanitize_list_filter('pending', 'company'));

        $GLOBALS['mjb_test_posts'] = array(11, 12);
        $GLOBALS['mjb_test_post_types'][11] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][12] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][11] = 'publish';
        $GLOBALS['mjb_test_post_status'][12] = 'publish';
        $GLOBALS['mjb_test_post_meta'][11][MJB_Analytics::VIEW_COUNT_META] = 8;
        $GLOBALS['mjb_test_post_meta'][12][MJB_Analytics::VIEW_COUNT_META] = 0;

        $ids = MJB_Admin_Dashboard::get_filtered_post_ids('zero_views', 'job_listing');
        $this->assertSame(array(12), $ids);

        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 2);
        $GLOBALS['mjb_test_post_counts']['job_application'] = (object) array('publish' => 0);
        $GLOBALS['mjb_test_post_counts']['company'] = (object) array('publish' => 0, 'pending' => 1);
        $snapshot = MJB_Admin_Dashboard::get_snapshot(30);
        $args = array();
        foreach ($snapshot['attention'] as $item) {
            $args[$item['title']] = isset($item['tab_args']['mjb_list']) ? $item['tab_args']['mjb_list'] : ($item['tab'] . ':' . (isset($item['tab_args']['settings_tab']) ? $item['tab_args']['settings_tab'] : ''));
        }
        $this->assertSame('zero_views', $args['Jobs with no views']);
        $this->assertSame('pending', $args['Companies awaiting approval']);
        $this->assertSame('no_apps', $args['No applications received on any listing']);
    }

    public function test_range_seven_marks_pressed_button()
    {
        $_REQUEST['range'] = 7;
        ob_start();
        MJB_Admin_Dashboard::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('data-range="7"', $html);
        $this->assertMatchesRegularExpression('/data-range="7"[^>]*aria-pressed="true"/', $html);
        $this->assertStringContainsString('in last 7 days', $html);
    }

    public function test_tab_nav_includes_counts_and_config_divider()
    {
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 4);
        $GLOBALS['mjb_test_post_counts']['job_application'] = (object) array('publish' => 1);
        $GLOBALS['mjb_test_post_counts']['company'] = (object) array('publish' => 2);
        $GLOBALS['mjb_test_post_counts']['mjb_resume'] = (object) array('publish' => 0);

        ob_start();
        MJB_Admin_Tabs::render_tab_nav('dashboard');
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-nav-burger', $html);
        $this->assertStringContainsString('mjb-admin-tabs__divider', $html);
        $this->assertStringContainsString('mjb-admin-tabs__list--config', $html);
        $this->assertStringContainsString('mjb-admin-tabs__count', $html);
        $this->assertStringContainsString('>4</span>', $html);
    }

    public function test_active_main_tab_is_not_a_link()
    {
        ob_start();
        MJB_Admin_Tabs::render_tab_nav('dashboard', true);
        $html = ob_get_clean();

        $this->assertMatchesRegularExpression('/<span class="mjb-admin-tabs__btn is-active"[^>]*id="mjb-tab-dashboard"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*id="mjb-tab-dashboard"/', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]+" class="mjb-admin-tabs__btn"[^>]*id="mjb-tab-jobs"/', $html);
    }

    public function test_shell_header_shows_free_plan_usage()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 3);

        ob_start();
        MJB_Admin_Tabs::render_shell_header();
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-topbar', $html);
        $this->assertStringContainsString('mjb-plan-chip', $html);
        $this->assertStringContainsString('3 of ' . MJB_License::FREE_ACTIVE_JOB_LIMIT . ' jobs used', $html);
        $this->assertStringContainsString('Upgrade', $html);
        $this->assertStringContainsString('Add new job', $html);
        $this->assertStringContainsString('Import jobs', $html);
        $this->assertStringContainsString('Export Jobs', $html);
        $this->assertStringContainsString('mjb-icon--download', $html);
        $export_start = strpos($html, 'id="mjb-export-jobs"');
        $this->assertNotFalse($export_start);
        $icon_pos = strpos($html, 'mjb-icon--download', $export_start);
        $label_pos = strpos($html, 'mjb-btn__label', $export_start);
        $this->assertNotFalse($icon_pos);
        $this->assertNotFalse($label_pos);
        $this->assertLessThan(
            $label_pos,
            $icon_pos,
            'Download icon must sit to the left of the Export Jobs label.'
        );
        $this->assertStringContainsString('Export jobs in CSV format', $html);
        $this->assertStringContainsString('Import jobs in CSV format', $html);
        $this->assertLessThan(
            strpos($html, 'Import jobs'),
            strpos($html, 'Export Jobs'),
            'Export Jobs must sit to the left of Import jobs in the topbar.'
        );
        $this->assertStringContainsString('wp-header-end', $html);
        $this->assertLessThan(
            strpos($html, 'mjb-topbar'),
            strpos($html, 'wp-header-end'),
            'Notices must land before the flex topbar, not inside it.'
        );
    }
}
