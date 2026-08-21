<?php

use PHPUnit\Framework\TestCase;

class JobOpsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_dates'] = array();
    }

    public function test_whatsapp_url_strips_non_digits()
    {
        $url = MJB_Job_Ops::whatsapp_url('+27 12-345 6789', 'Hello');
        $this->assertStringContainsString('https://wa.me/27123456789', $url);
        $this->assertStringContainsString('text=', $url);
    }

    public function test_parse_emails_accepts_list()
    {
        $emails = MJB_Job_Ops::parse_emails('a@example.com, b@example.com; bad; c@test.org');
        $this->assertSame(array('a@example.com', 'b@example.com', 'c@test.org'), $emails);
    }

    public function test_is_filled_reads_meta()
    {
        $GLOBALS['mjb_test_post_meta'][10][MJB_Job_Ops::META_FILLED] = '1';
        $this->assertTrue(MJB_Job_Ops::is_filled(10));
        $GLOBALS['mjb_test_post_meta'][10][MJB_Job_Ops::META_FILLED] = '0';
        $this->assertFalse(MJB_Job_Ops::is_filled(10));
    }

    public function test_filter_listing_query_hides_filled_when_enabled()
    {
        $GLOBALS['mjb_test_options'][MJB_Job_Ops::OPTION_HIDE_FILLED] = '1';
        $args = MJB_Job_Ops::filter_listing_query(array('post_type' => 'job_listing'), array());
        $this->assertArrayHasKey('meta_query', $args);
        $this->assertNotEmpty($args['meta_query']);
    }

    public function test_is_rendering_related_defaults_false()
    {
        $this->assertFalse(MJB_Job_Ops::is_rendering_related());
    }
}
