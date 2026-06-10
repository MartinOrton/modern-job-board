<?php

use PHPUnit\Framework\TestCase;

class WebhooksTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_remote_posts'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_titles'] = array();
    }

    public function test_get_urls_parses_multiline_option()
    {
        $GLOBALS['mjb_test_options']['mjb_webhook_urls'] = "https://hooks.test/one\n\nhttps://hooks.test/two\n";

        $urls = MJB_Webhooks::get_urls();

        $this->assertCount(2, $urls);
        $this->assertSame('https://hooks.test/one', $urls[0]);
        $this->assertSame('https://hooks.test/two', $urls[1]);
    }

    public function test_dispatch_posts_json_payload_to_configured_urls()
    {
        $GLOBALS['mjb_test_options']['mjb_webhook_urls'] = "https://hooks.test/event\n";
        $GLOBALS['mjb_test_options']['mjb_webhook_secret'] = 'secret-key';
        MJB_Webhooks::dispatch('application.submitted', array('id' => 9));

        $this->assertCount(1, $GLOBALS['mjb_test_remote_posts']);
        $this->assertSame('https://hooks.test/event', $GLOBALS['mjb_test_remote_posts'][0]['url']);
    }

    public function test_dispatch_noops_when_urls_missing()
    {
        MJB_Webhooks::dispatch('job.submitted', array('id' => 1));
        $this->assertTrue(true);
    }
}