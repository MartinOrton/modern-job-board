<?php

use PHPUnit\Framework\TestCase;

class AnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_transients'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_is_logged_in'] = false;
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_db_results'] = array();
    }

    public function test_record_job_view_increments_once_per_visitor()
    {
        $GLOBALS['mjb_test_post_meta'][10][MJB_Analytics::VIEW_COUNT_META] = 2;

        MJB_Analytics::record_job_view(10);
        MJB_Analytics::record_job_view(10);

        $this->assertSame(3, intval(get_post_meta(10, MJB_Analytics::VIEW_COUNT_META, true)));
    }

    public function test_get_job_stats_calculates_conversion_rate()
    {
        $GLOBALS['mjb_test_post_meta'][20][MJB_Analytics::VIEW_COUNT_META] = 100;
        $GLOBALS['mjb_test_post_status'][20] = 'publish';
        $GLOBALS['mjb_test_titles'][20] = 'Engineer';
        $GLOBALS['mjb_test_db_results'] = array(
            array('job_id' => '20', 'app_count' => '4'),
        );

        $stats = MJB_Analytics::get_job_stats(20);

        $this->assertSame(100, $stats['views']);
        $this->assertSame(4.0, $stats['conversion_rate']);
    }

    public function test_summarize_job_stats_totals_rows()
    {
        $summary = MJB_Analytics::summarize_job_stats(array(
            array('views' => 50, 'applications' => 5),
            array('views' => 50, 'applications' => 5),
        ));

        $this->assertSame(2, $summary['jobs']);
        $this->assertSame(100, $summary['views']);
        $this->assertSame(10, $summary['applications']);
        $this->assertSame(10.0, $summary['conversion_rate']);
    }

    public function test_get_top_jobs_for_charts_sorts_by_views()
    {
        $GLOBALS['mjb_test_posts'] = array(1, 2, 3);
        $GLOBALS['mjb_test_post_types'][1] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][2] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][3] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][1] = 'publish';
        $GLOBALS['mjb_test_post_status'][2] = 'publish';
        $GLOBALS['mjb_test_post_status'][3] = 'publish';
        $GLOBALS['mjb_test_titles'][1] = 'Low';
        $GLOBALS['mjb_test_titles'][2] = 'High';
        $GLOBALS['mjb_test_titles'][3] = 'Mid';
        $GLOBALS['mjb_test_post_meta'][1][MJB_Analytics::VIEW_COUNT_META] = 1;
        $GLOBALS['mjb_test_post_meta'][2][MJB_Analytics::VIEW_COUNT_META] = 30;
        $GLOBALS['mjb_test_post_meta'][3][MJB_Analytics::VIEW_COUNT_META] = 10;
        $GLOBALS['mjb_test_db_results'] = array();

        $top = MJB_Analytics::get_top_jobs_for_charts(2);

        $this->assertCount(2, $top);
        $this->assertSame('High', $top[0]['title']);
        $this->assertSame(30, $top[0]['views']);
    }

    public function test_chart_width_class_returns_utility_class()
    {
        $this->assertSame('mjb-chart-w-0', MJB_Analytics::chart_width_class(0));
        $this->assertSame('mjb-chart-w-45', MJB_Analytics::chart_width_class(44.6));
        $this->assertSame('mjb-chart-w-100', MJB_Analytics::chart_width_class(150));
    }

    public function test_render_admin_charts_html_uses_width_utilities_not_embedded_styles()
    {
        $html = MJB_Analytics::render_admin_charts_html(array(
            array('title' => 'Engineer', 'views' => 80, 'applications' => 4),
            array('title' => 'Designer', 'views' => 40, 'applications' => 2),
        ));

        $this->assertStringContainsString('mjb-chart-w-100', $html);
        $this->assertStringContainsString('mjb-chart-w-50', $html);
        $this->assertStringNotContainsString('<style', $html);
        $this->assertStringNotContainsString('style=', $html);
    }
}