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
}