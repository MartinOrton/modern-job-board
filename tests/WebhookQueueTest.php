<?php

use PHPUnit\Framework\TestCase;

class WebhookQueueTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_remote_posts'] = array();
        $GLOBALS['mjb_test_remote_response_code'] = 204;
    }

    public function test_enqueue_stores_failed_delivery()
    {
        $GLOBALS['mjb_test_remote_response_code'] = 500;

        MJB_Webhook_Queue::deliver('application.submitted', 'https://hooks.test/fail', '{"ok":false}', array(), 0);

        $queue = MJB_Webhook_Queue::get_queue();
        $this->assertCount(1, $queue);
        $this->assertSame(1, $queue[0]['attempt']);
    }

    public function test_process_queue_retries_until_success()
    {
        $GLOBALS['mjb_test_options'][MJB_Webhook_Queue::OPTION_KEY] = array(
            array(
                'event' => 'job.submitted',
                'url' => 'https://hooks.test/retry',
                'body' => '{"id":1}',
                'headers' => array(),
                'attempt' => 1,
                'next_retry' => time() - 10,
            ),
        );

        MJB_Webhook_Queue::process_queue();

        $this->assertCount(0, MJB_Webhook_Queue::get_queue());
        $this->assertCount(1, $GLOBALS['mjb_test_remote_posts']);
    }

    public function test_get_backoff_seconds_uses_exponential_steps()
    {
        $this->assertSame(60, MJB_Webhook_Queue::get_backoff_seconds(1));
        $this->assertSame(300, MJB_Webhook_Queue::get_backoff_seconds(2));
        $this->assertSame(21600, MJB_Webhook_Queue::get_backoff_seconds(5));
    }
}