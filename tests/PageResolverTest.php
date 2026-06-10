<?php

use PHPUnit\Framework\TestCase;

class PageResolverTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_content'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
    }

    public function test_resolve_page_id_returns_cached_option()
    {
        $GLOBALS['mjb_test_options']['mjb_test_page_id'] = 12;
        $GLOBALS['mjb_test_post_status'][12] = 'publish';

        $this->assertSame(12, MJB_Page_Resolver::resolve_page_id('mjb_dashboard', 'mjb_test_page_id'));
    }

    public function test_resolve_page_id_scans_pages_for_shortcode()
    {
        $GLOBALS['mjb_test_posts'] = array(5, 8);
        $GLOBALS['mjb_test_post_status'][5] = 'publish';
        $GLOBALS['mjb_test_post_status'][8] = 'publish';
        $GLOBALS['mjb_test_post_content'][8] = '[mjb_job_form]';

        $this->assertSame(8, MJB_Page_Resolver::resolve_page_id('mjb_job_form', 'mjb_job_form_page_id'));
        $this->assertSame(8, $GLOBALS['mjb_test_options']['mjb_job_form_page_id']);
    }

    public function test_get_page_url_uses_permalink_when_page_found()
    {
        $GLOBALS['mjb_test_options']['mjb_test_page_id'] = 20;
        $GLOBALS['mjb_test_post_status'][20] = 'publish';
        $GLOBALS['mjb_test_permalinks'][20] = 'https://example.test/job-dashboard/';

        $url = MJB_Page_Resolver::get_page_url('mjb_dashboard', 'mjb_test_page_id', array('action' => 'edit'));

        $this->assertSame('https://example.test/job-dashboard/?action=edit', $url);
    }

    public function test_get_page_url_falls_back_to_home_path()
    {
        $url = MJB_Page_Resolver::get_page_url('mjb_job_form', 'mjb_missing_page', array(), '/post-job/');

        $this->assertSame('https://example.test/post-job/', $url);
    }

    public function test_invalidate_if_cached_page_clears_matching_option()
    {
        $GLOBALS['mjb_test_options']['mjb_employer_dashboard_page_id'] = 44;
        $GLOBALS['mjb_test_options']['mjb_job_form_page_id'] = 55;

        MJB_Page_Resolver::invalidate_if_cached_page(44);

        $this->assertArrayNotHasKey('mjb_employer_dashboard_page_id', $GLOBALS['mjb_test_options']);
        $this->assertSame(55, $GLOBALS['mjb_test_options']['mjb_job_form_page_id']);
    }
}