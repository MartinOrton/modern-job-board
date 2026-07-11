<?php

use PHPUnit\Framework\TestCase;

class ImportSchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array(
            'admin_email' => 'admin@example.test',
        );
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_terms'] = array();
        $GLOBALS['mjb_test_companies_by_title'] = array();
        $GLOBALS['mjb_test_inserted_posts'] = array();
        $GLOBALS['mjb_test_next_post_id'] = 3000;
        $GLOBALS['mjb_test_current_user_id'] = 5;
        $GLOBALS['mjb_test_remote_get_responses'] = array();
        $GLOBALS['mjb_test_mails'] = array();
        $GLOBALS['mjb_test_timestamp'] = 1700000000;

        update_option(MJB_Import_Scheduler::OPTION_KEY, array());
    }

    public function test_save_feed_creates_enabled_daily_feed()
    {
        $feed_id = MJB_Import_Scheduler::save_feed(array(
            'name' => 'Partner Jobs',
            'url' => 'https://feeds.test/partner.xml',
            'schedule' => 'daily',
            'enabled' => true,
            'author_id' => 5,
        ));

        $this->assertIsString($feed_id);
        $feeds = MJB_Import_Scheduler::get_feeds();
        $this->assertCount(1, $feeds);
        $this->assertSame('Partner Jobs', $feeds[0]['name']);
        $this->assertSame('daily', $feeds[0]['schedule']);
        $this->assertTrue($feeds[0]['enabled']);
        $this->assertSame(5, $feeds[0]['author_id']);
    }

    public function test_is_feed_due_respects_daily_and_weekly_intervals()
    {
        $daily = array(
            'schedule' => 'daily',
            'last_run' => gmdate('Y-m-d H:i:s', $GLOBALS['mjb_test_timestamp'] - DAY_IN_SECONDS + 60),
        );
        $weekly = array(
            'schedule' => 'weekly',
            'last_run' => gmdate('Y-m-d H:i:s', $GLOBALS['mjb_test_timestamp'] - WEEK_IN_SECONDS + 60),
        );

        $this->assertFalse(MJB_Import_Scheduler::is_feed_due($daily));
        $this->assertFalse(MJB_Import_Scheduler::is_feed_due($weekly));

        $daily['last_run'] = gmdate('Y-m-d H:i:s', $GLOBALS['mjb_test_timestamp'] - DAY_IN_SECONDS - 1);
        $weekly['last_run'] = gmdate('Y-m-d H:i:s', $GLOBALS['mjb_test_timestamp'] - WEEK_IN_SECONDS - 1);

        $this->assertTrue(MJB_Import_Scheduler::is_feed_due($daily));
        $this->assertTrue(MJB_Import_Scheduler::is_feed_due($weekly));
        $this->assertTrue(MJB_Import_Scheduler::is_feed_due(array('schedule' => 'daily', 'last_run' => '')));
    }

    public function test_run_feed_imports_jobs_and_updates_status()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Scheduled Role</title>
      <guid>scheduled-role-1</guid>
      <description>Imported by cron</description>
    </item>
  </channel>
</rss>
XML;

        $GLOBALS['mjb_test_remote_get_responses']['https://feeds.test/cron.xml'] = array(
            'response' => array('code' => 200),
            'body' => $xml,
        );

        $feed_id = MJB_Import_Scheduler::save_feed(array(
            'id' => 'feed_test123',
            'name' => 'Cron Feed',
            'url' => 'https://feeds.test/cron.xml',
            'schedule' => 'daily',
            'enabled' => true,
            'author_id' => 9,
        ));

        $result = MJB_Import_Scheduler::run_feed($feed_id, true);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['imported']);

        $feed = MJB_Import_Scheduler::get_feed($feed_id);
        $this->assertSame('success', $feed['last_status']);
        $this->assertSame(1, $feed['last_imported']);
        $this->assertSame(9, $feed['author_id']);
        $this->assertNotEmpty($feed['last_run']);
        $this->assertSame(9, $GLOBALS['mjb_test_post_authors'][array_key_last($GLOBALS['mjb_test_inserted_posts'])]);
    }

    public function test_run_feed_failure_emails_admin_and_records_error()
    {
        $feed_id = MJB_Import_Scheduler::save_feed(array(
            'id' => 'feed_fail123',
            'name' => 'Broken Feed',
            'url' => 'https://feeds.test/missing.xml',
            'schedule' => 'daily',
            'enabled' => true,
            'author_id' => 1,
        ));

        $result = MJB_Import_Scheduler::run_feed($feed_id, true);

        $this->assertInstanceOf(WP_Error::class, $result);

        $feed = MJB_Import_Scheduler::get_feed($feed_id);
        $this->assertSame('error', $feed['last_status']);
        $this->assertNotEmpty($feed['last_error']);
        $this->assertCount(1, $GLOBALS['mjb_test_mails']);
        $this->assertSame('admin@example.test', $GLOBALS['mjb_test_mails'][0]['to']);
    }

    public function test_run_due_feeds_skips_disabled_and_not_due_feeds()
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>Due Role</title>
      <guid>due-role-1</guid>
      <description>Should import</description>
    </item>
  </channel>
</rss>
XML;

        $GLOBALS['mjb_test_remote_get_responses']['https://feeds.test/due.xml'] = array(
            'response' => array('code' => 200),
            'body' => $xml,
        );

        MJB_Import_Scheduler::save_feed(array(
            'id' => 'feed_due',
            'name' => 'Due Feed',
            'url' => 'https://feeds.test/due.xml',
            'schedule' => 'daily',
            'enabled' => true,
            'author_id' => 1,
        ));

        MJB_Import_Scheduler::save_feed(array(
            'id' => 'feed_recent',
            'name' => 'Recent Feed',
            'url' => 'https://feeds.test/recent.xml',
            'schedule' => 'daily',
            'enabled' => true,
            'author_id' => 1,
        ));

        MJB_Import_Scheduler::update_feed_status('feed_recent', array(
            'last_run' => gmdate('Y-m-d H:i:s', $GLOBALS['mjb_test_timestamp'] - 3600),
            'last_status' => 'success',
        ));

        MJB_Import_Scheduler::save_feed(array(
            'id' => 'feed_disabled',
            'name' => 'Disabled Feed',
            'url' => 'https://feeds.test/disabled.xml',
            'schedule' => 'daily',
            'enabled' => false,
            'author_id' => 1,
        ));

        MJB_Import_Scheduler::run_due_feeds();

        $due = MJB_Import_Scheduler::get_feed('feed_due');
        $recent = MJB_Import_Scheduler::get_feed('feed_recent');
        $disabled = MJB_Import_Scheduler::get_feed('feed_disabled');

        $this->assertSame('success', $due['last_status']);
        $this->assertSame('success', $recent['last_status']);
        $this->assertSame('', $disabled['last_status']);
    }

    public function test_delete_feed_removes_entry()
    {
        $feed_id = MJB_Import_Scheduler::save_feed(array(
            'name' => 'Delete Me',
            'url' => 'https://feeds.test/delete.xml',
            'schedule' => 'weekly',
            'enabled' => true,
            'author_id' => 1,
        ));

        $this->assertTrue(MJB_Import_Scheduler::delete_feed($feed_id));
        $this->assertCount(0, MJB_Import_Scheduler::get_feeds());
    }
}